@extends('layouts.app', ['title' => $user->exists ? 'Edit Pengguna' : 'Tambah Pengguna Baru'])

@section('content')
<div class="glass p-6 max-w-2xl mx-auto">
    <div class="orn-line mb-2"></div>
    <h2 class="text-lg font-extrabold text-white mb-4">{{ $user->exists ? 'Perbarui Akun '.$user->name : 'Buat Akun Pengguna Baru' }}</h2>

    <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}" class="space-y-4">
        @csrf
        @if($user->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="input">
            </div>
            <div>
                <label class="label">Role Hak Akses</label>
                <select name="role" class="input" required>
                    @foreach(\App\Models\User::ROLES as $rk => $rl)
                        <option value="{{ $rk }}" @selected(old('role', $user->role) === $rk)>{{ $rl }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Email Login</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input">
            </div>
            <div>
                <label class="label">Nomor WhatsApp</label>
                <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0812xxxxxxxx" class="input">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="label">NIK</label>
                <input type="text" name="nik" value="{{ old('nik', $user->nik) }}" placeholder="FL-0001" class="input">
            </div>
            <div>
                <label class="label">Bidang / Spesialisasi</label>
                <input type="text" name="specialization" value="{{ old('specialization', $user->specialization) }}" placeholder="Operasional Gerai" class="input">
            </div>
            <div>
                <label class="label">Tarif per Jam (Rp)</label>
                <input type="number" step="500" min="0" name="hourly_rate" value="{{ old('hourly_rate', $user->hourly_rate ?? 0) }}" class="input">
            </div>
        </div>

        <label class="inline-flex items-center gap-2.5 cursor-pointer pt-1">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active ?? true)) class="check">
            <span class="text-sm text-white font-semibold">Akun Aktif (Dapat Login)</span>
        </label>

        @if(!$user->exists)
            <p class="text-xs text-slate-400">
                Password acak berkekuatan tinggi akan dibuat otomatis dan siap dikirim melalui WhatsApp setelah disimpan.
            </p>
        @endif

        <div class="flex items-center justify-end gap-2 pt-3">
            <a href="{{ route('admin.users.index') }}" class="btn btn-ghost">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Pengguna</button>
        </div>
    </form>
</div>
@endsection
