<?php

namespace App\Http\Controllers\Hrd;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\PerformanceLog;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FreelancerController extends Controller
{
    public function index(Request $request)
    {
        $freelancers = User::freelancers()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$request->q}%")->orWhere('email', 'like', "%{$request->q}%")->orWhere('nik', 'like', "%{$request->q}%")->orWhere('phone', 'like', "%{$request->q}%")))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->withCount(['tasks as tasks_30d' => fn ($q) => $q->where('assigned_date', '>=', today()->subDays(30))])
            ->withAvg(['performanceLogs as kpi_30d' => fn ($q) => $q->where('evaluation_date', '>=', today()->subDays(30))], 'final_kpi_score')
            ->orderBy('name')->paginate(12)->withQueryString();

        return view('hrd.freelancers.index', ['freelancers' => $freelancers]);
    }

    public function create()
    {
        return view('hrd.freelancers.form', ['freelancer' => new User(['is_active' => true, 'role' => User::ROLE_FREELANCER])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plain = PasswordGenerator::generate();
        $freelancer = User::create($data + ['role' => User::ROLE_FREELANCER, 'password' => $plain]);
        ActivityLogger::log('freelancer.created', "Mendaftarkan karyawan {$freelancer->name}", $freelancer);

        return redirect()->route('hrd.freelancers.index')
            ->with('success', "Karyawan {$freelancer->name} berhasil didaftarkan.")
            ->with('credential', PasswordGenerator::credentialPayload($freelancer, $plain, 'Akun freelancer Anda telah dibuat'));
    }

    public function show(User $freelancer)
    {
        $this->ensureFreelancer($freelancer);

        $logs = PerformanceLog::where('user_id', $freelancer->id)->where('evaluation_date', '>=', today()->subDays(29))->orderBy('evaluation_date')->get();

        return view('hrd.freelancers.show', [
            'freelancer' => $freelancer,
            'logs' => $logs,
            'avgKpi' => round((float) $logs->avg('final_kpi_score'), 2),
            'tasks' => TaskAssignment::with('outlet:id,name')->where('user_id', $freelancer->id)->latest('assigned_date')->latest('id')->limit(10)->get(),
            'attendances' => Attendance::where('user_id', $freelancer->id)->latest('date')->limit(10)->get(),
            'taskStats' => [
                'total' => TaskAssignment::where('user_id', $freelancer->id)->notCancelled()->count(),
                'done' => TaskAssignment::where('user_id', $freelancer->id)->where('status', 'completed')->count(),
            ],
        ]);
    }

    public function edit(User $freelancer)
    {
        $this->ensureFreelancer($freelancer);

        return view('hrd.freelancers.form', ['freelancer' => $freelancer]);
    }

    public function update(Request $request, User $freelancer): RedirectResponse
    {
        $this->ensureFreelancer($freelancer);
        $freelancer->update($this->validated($request, $freelancer));
        ActivityLogger::log('freelancer.updated', "Memperbarui karyawan {$freelancer->name}", $freelancer);

        return redirect()->route('hrd.freelancers.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }

    public function toggle(User $freelancer): RedirectResponse
    {
        $this->ensureFreelancer($freelancer);
        $freelancer->update(['is_active' => ! $freelancer->is_active]);
        if (! $freelancer->is_active) {
            DB::table('sessions')->where('user_id', $freelancer->id)->delete();
        }
        ActivityLogger::log($freelancer->is_active ? 'freelancer.activated' : 'freelancer.deactivated', ($freelancer->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." karyawan {$freelancer->name}", $freelancer);

        return back()->with('success', 'Status karyawan '.($freelancer->is_active ? 'diaktifkan.' : 'dinonaktifkan.'));
    }

    public function destroy(User $freelancer): RedirectResponse
    {
        $this->ensureFreelancer($freelancer);
        if ($freelancer->tasks()->exists() || $freelancer->attendances()->exists() || $freelancer->performanceLogs()->exists()) {
            return back()->with('error', 'Karyawan memiliki riwayat tugas/presensi. Nonaktifkan akun alih-alih menghapus.');
        }
        $name = $freelancer->name;
        $freelancer->delete();
        ActivityLogger::log('freelancer.deleted', "Menghapus karyawan {$name}");

        return redirect()->route('hrd.freelancers.index')->with('success', 'Karyawan dihapus.');
    }

    /** HRD tidak mereset langsung; permintaan dicatat dan muncul di dashboard SuperAdmin. */
    public function requestReset(User $freelancer): RedirectResponse
    {
        $this->ensureFreelancer($freelancer);
        ActivityLogger::log('password.reset_requested', "Permintaan reset password untuk {$freelancer->name} (#{$freelancer->id})", $freelancer);

        return back()->with('success', 'Permintaan reset password dikirim ke SuperAdmin.');
    }

    protected function ensureFreelancer(User $user): void
    {
        abort_unless($user->role === User::ROLE_FREELANCER, 404);
    }

    protected function validated(Request $request, ?User $freelancer = null): array
    {
        $data = $request->validate([
            'nik' => ['required', 'string', 'max:30', Rule::unique('users', 'nik')->ignore($freelancer?->id)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($freelancer?->id)],
            'phone' => ['required', 'string', 'max:25', 'regex:/^[0-9+\-\s]{6,25}$/'],
            'specialization' => ['nullable', 'string', 'max:150'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ], ['phone.regex' => 'Format nomor WhatsApp tidak valid.', 'email.unique' => 'Email sudah digunakan.', 'nik.unique' => 'NIK sudah digunakan.']);

        $data['hourly_rate'] = $data['hourly_rate'] ?? 0;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
