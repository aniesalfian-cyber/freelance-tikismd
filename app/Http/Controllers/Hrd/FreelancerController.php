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
        $query = User::freelancers()
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$request->q}%")->orWhere('email', 'like', "%{$request->q}%")->orWhere('nik', 'like', "%{$request->q}%")->orWhere('phone', 'like', "%{$request->q}%")))
            ->when($request->filled('division'), fn ($q) => $q->where('specialization', $request->division))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->withCount(['tasks as tasks_30d' => fn ($q) => $q->where('assigned_date', '>=', today()->subDays(30))])
            ->withAvg(['performanceLogs as kpi_30d' => fn ($q) => $q->where('evaluation_date', '>=', today()->subDays(30))], 'final_kpi_score');

        // Ambil data personel kurir dan kelompokkan berdasarkan 4 Divisi Operasional
        $freelancers = $query->orderBy('name')->get();
        $grouped = $freelancers->groupBy('specialization');

        return view('hrd.freelancers.index', [
            'freelancers' => $freelancers,
            'grouped' => $grouped,
            'divisions' => User::DIVISIONS,
        ]);
    }

    public function create()
    {
        return view('hrd.freelancers.form', [
            'freelancer' => new User(['is_active' => true, 'role' => User::ROLE_FREELANCER, 'specialization' => User::DIVISION_R2_DELIVERY]),
            'divisions' => User::DIVISIONS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plain = PasswordGenerator::generate();
        $freelancer = User::create($data + ['role' => User::ROLE_FREELANCER, 'password' => $plain]);
        ActivityLogger::log('freelancer.created', "Mendaftarkan karyawan {$freelancer->name} di {$freelancer->specialization}", $freelancer);

        return redirect()->route('hrd.freelancers.index')
            ->with('success', "Karyawan {$freelancer->name} berhasil didaftarkan pada divisi {$freelancer->specialization}.")
            ->with('credential', PasswordGenerator::credentialPayload($freelancer, $plain, 'Akun karyawan Anda telah dibuat'));
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
                'total' => TaskAssignment::where('user_id', $freelancer->id)->count(),
                'completed' => TaskAssignment::where('user_id', $freelancer->id)->where('status', TaskAssignment::STATUS_COMPLETED)->count(),
                'ontime' => TaskAssignment::where('user_id', $freelancer->id)->where('status', TaskAssignment::STATUS_COMPLETED)->where('is_location_valid', true)->count(),
            ],
            'divisions' => User::DIVISIONS,
        ]);
    }

    public function edit(User $freelancer)
    {
        $this->ensureFreelancer($freelancer);

        return view('hrd.freelancers.form', [
            'freelancer' => $freelancer,
            'divisions' => User::DIVISIONS,
        ]);
    }

    public function update(Request $request, User $freelancer): RedirectResponse
    {
        $this->ensureFreelancer($freelancer);
        $data = $this->validated($request, $freelancer->id);
        $freelancer->update($data);
        ActivityLogger::log('freelancer.updated', "Memperbarui data karyawan {$freelancer->name}", $freelancer);

        return redirect()->route('hrd.freelancers.index')->with('success', "Data karyawan {$freelancer->name} berhasil diperbarui.");
    }

    public function requestReset(User $freelancer): RedirectResponse
    {
        $this->ensureFreelancer($freelancer);
        ActivityLogger::log('freelancer.reset_requested', "Admin HRD meminta reset password untuk {$freelancer->name} (No: {$freelancer->phone})", $freelancer);

        return back()->with('success', "Permintaan reset password untuk {$freelancer->name} telah dicatat dalam log sistem. Hubungi SuperAdmin untuk mereset.");
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'nik' => ['required', 'string', 'max:30', Rule::unique('users', 'nik')->ignore($ignoreId)],
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($ignoreId)],
            'phone' => ['required', 'string', 'max:25'],
            'specialization' => ['required', 'string', Rule::in(array_keys(User::DIVISIONS))],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['hourly_rate'] = $data['hourly_rate'] ?? 0;

        return $data;
    }

    protected function ensureFreelancer(User $user): void
    {
        if (! $user->isFreelancer()) {
            abort(404);
        }
    }
}
