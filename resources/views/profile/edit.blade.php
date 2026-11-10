@extends('layouts.app', ['title' => 'Profil & Keamanan Akun', 'subtitle' => 'Kelola identitas dan kredensial login Anda'])

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Summary Card -->
    <div class="glass p-6 space-y-4">
        <div class="flex items-center gap-4">
            @if($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-2xl object-cover border border-orange-500/40">
            @else
                <div class="w-16 h-16 rounded-2xl bg-orange-500/20 border border-orange-400/40 flex items-center justify-center text-xl font-extrabold text-orange-300">
                    {{ $user->initials() }}
                </div>
            @endif
            <div class="min-w-0">
                <span class="badge badge-orange">{{ $user->roleLabel() }}</span>
                <h2 class="text-lg font-extrabold text-white truncate mt-1">{{ $user->name }}</h2>
                <p class="text-xs text-slate-400 truncate">{{ $user->email }}</p>
            </div>
        </div>
        <div class="divider"></div>
        <dl class="space-y-2 text-xs">
            <div class="flex justify-between">
                <dt class="text-slate-400">NIK:</dt>
                <dd class="font-mono font-semibold text-white">{{ $user->nik ?? '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-400">No. WhatsApp:</dt>
                <dd class="font-semibold text-white">{{ $user->phone ?? '-' }}</dd>
            </div>
            @if($user->isFreelancer())
                <div class="flex justify-between">
                    <dt class="text-slate-400">Bidang / Spesialisasi:</dt>
                    <dd class="font-semibold text-white">{{ $user->specialization ?? 'Operasional Umum' }}</dd>
                </div>
            @endif
            <div class="flex justify-between">
                <dt class="text-slate-400">Login Terakhir:</dt>
                <dd class="text-slate-300">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('logout') }}" class="pt-2">
            @csrf
            <button type="submit" class="btn btn-danger btn-block">
                <x-icon name="logout" class="w-4 h-4" /> Keluar dari Sesi
            </button>
        </form>
    </div>

    <!-- Edit Profile & Password -->
    <div class="lg:col-span-2 space-y-6">
        <div class="glass p-6">
            <div class="orn-line mb-2"></div>
            <h3 class="text-base font-extrabold text-white mb-4">Perbarui Informasi Profil</h3>
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Nama Lengkap</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="input">
                    </div>
                    <div>
                        <label class="label">Nomor WhatsApp</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0812xxxxxxxx" class="input">
                    </div>
                </div>
                <div>
                    <label class="label">Foto Profil (Opsional, maks 2MB)</label>
                    <input type="file" name="avatar" accept="image/*" class="input">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>

        <div class="glass p-6">
            <div class="orn-line mb-2"></div>
            <h3 class="text-base font-extrabold text-white mb-4">Ganti Password Akun</h3>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="label">Password Saat Ini</label>
                    <input type="password" name="current_password" required class="input">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Password Baru (min. 8 karakter huruf & angka)</label>
                        <input type="password" name="password" required class="input">
                    </div>
                    <div>
                        <label class="label">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" required class="input">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">Perbarui Password</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
