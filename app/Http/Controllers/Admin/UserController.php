<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\PasswordGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->when($request->filled('q'), fn ($q) => $q->where(function ($w) use ($request) {
                $w->where('name', 'like', "%{$request->q}%")->orWhere('email', 'like', "%{$request->q}%")->orWhere('nik', 'like', "%{$request->q}%");
            }))
            ->orderBy('role')->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(['role' => User::ROLE_FREELANCER, 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plain = PasswordGenerator::generate();
        $user = User::create($data + ['password' => $plain]);
        ActivityLogger::log('user.created', "Membuat akun {$user->name} ({$user->roleLabel()})", $user);

        return redirect()->route('admin.users.index')
            ->with('success', "Akun {$user->name} berhasil dibuat.")
            ->with('credential', PasswordGenerator::credentialPayload($user, $plain, 'Akun Anda telah dibuat'));
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);

        if ($user->id === $request->user()->id) {
            // Cegah SuperAdmin mengunci dirinya sendiri.
            $data['role'] = $user->role;
            $data['is_active'] = true;
        } elseif ($user->isSuperadmin() && ($data['role'] !== User::ROLE_SUPERADMIN || ! $data['is_active'])) {
            if (User::where('role', User::ROLE_SUPERADMIN)->where('is_active', true)->where('id', '!=', $user->id)->doesntExist()) {
                return back()->withInput()->with('error', 'Minimal harus ada satu SuperAdmin aktif.');
            }
        }

        $user->update($data);
        ActivityLogger::log('user.updated', "Memperbarui akun {$user->name}", $user);

        return redirect()->route('admin.users.index')->with('success', 'Data pengguna berhasil diperbarui.');
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
        }
        if ($user->isSuperadmin() && $user->is_active
            && User::where('role', User::ROLE_SUPERADMIN)->where('is_active', true)->where('id', '!=', $user->id)->doesntExist()) {
            return back()->with('error', 'Minimal harus ada satu SuperAdmin aktif.');
        }

        $user->update(['is_active' => ! $user->is_active]);
        // Hapus sesi aktif pengguna agar pencabutan akses berlaku seketika.
        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        ActivityLogger::log($user->is_active ? 'user.activated' : 'user.deactivated', ($user->is_active ? 'Mengaktifkan' : 'Menonaktifkan')." akun {$user->name}", $user);

        return back()->with('success', 'Akun '.($user->is_active ? 'diaktifkan.' : 'dinonaktifkan dan sesi login dicabut.'));
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $plain = PasswordGenerator::generate();
        $user->update(['password' => $plain]);
        DB::table('sessions')->where('user_id', $user->id)->where('user_id', '!=', $request->user()->id)->delete();
        ActivityLogger::log('password.reset', "Reset password akun {$user->name}", $user);

        return back()
            ->with('success', "Password {$user->name} berhasil direset.")
            ->with('credential', PasswordGenerator::credentialPayload($user, $plain, 'Password akun Anda telah direset'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }
        if ($user->tasks()->exists() || $user->attendances()->exists() || $user->performanceLogs()->exists()) {
            return back()->with('error', 'Akun memiliki riwayat tugas/presensi. Nonaktifkan akun alih-alih menghapus.');
        }
        if ($user->isSuperadmin() && User::where('role', User::ROLE_SUPERADMIN)->where('id', '!=', $user->id)->doesntExist()) {
            return back()->with('error', 'Minimal harus ada satu SuperAdmin.');
        }

        $name = $user->name;
        $user->delete();
        ActivityLogger::log('user.deleted', "Menghapus akun {$name}");

        return redirect()->route('admin.users.index')->with('success', 'Akun berhasil dihapus.');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'nik' => ['nullable', 'string', 'max:30', Rule::unique('users', 'nik')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s]{6,25}$/'],
            'role' => ['required', Rule::in(array_keys(User::ROLES))],
            'specialization' => ['nullable', 'string', 'max:150'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ], ['phone.regex' => 'Format nomor telepon tidak valid.', 'email.unique' => 'Email sudah digunakan.', 'nik.unique' => 'NIK sudah digunakan.']);

        $data['is_active'] = $request->boolean('is_active');
        $data['hourly_rate'] = $data['hourly_rate'] ?? 0;
        $data['nik'] = $data['nik'] ?: null;

        return $data;
    }
}
