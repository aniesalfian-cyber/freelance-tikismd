@extends('layouts.app', ['title' => 'Profil & Keamanan Akun', 'subtitle' => 'Kelola foto profil, nama, nomor HP, PIN 4 digit, dan password Anda'])

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 rise">
    <!-- Summary Card -->
    <div class="glass p-6 space-y-4 h-fit">
        <div class="flex items-center gap-4">
            @if($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-16 h-16 rounded-2xl object-cover border-2 border-orange-500/50 shadow-sm">
            @else
                <div class="w-16 h-16 rounded-2xl bg-orange-500 text-white border border-orange-600 flex items-center justify-center text-xl font-black shadow-sm">
                    {{ $user->initials() }}
                </div>
            @endif
            <div class="min-w-0">
                <span class="badge badge-orange">{{ $user->roleLabel() }}</span>
                <h2 class="text-lg font-extrabold text-slate-900 truncate mt-1">{{ $user->name }}</h2>
                <p class="text-xs text-slate-500 truncate">{{ $user->phone ?: $user->email }}</p>
            </div>
        </div>
        <div class="divider"></div>
        <dl class="space-y-2.5 text-xs">
            <div class="flex justify-between">
                <dt class="text-slate-500">NIK:</dt>
                <dd class="font-mono font-bold text-slate-900">{{ $user->nik ?? '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">No. Handphone / WA:</dt>
                <dd class="font-bold text-slate-900">{{ $user->phone ?? '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Email:</dt>
                <dd class="font-semibold text-slate-800 truncate max-w-44">{{ $user->email }}</dd>
            </div>
            @if($user->isFreelancer())
                <div class="flex justify-between">
                    <dt class="text-slate-500">Divisi Operasional:</dt>
                    <dd class="font-bold text-slate-900">{{ $user->specialization ?? 'Operasional Kurir' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Armada:</dt>
                    <dd class="font-semibold text-slate-800">{{ $user->vehicleName() }}</dd>
                </div>
            @endif
            <div class="flex justify-between">
                <dt class="text-slate-500">Status PIN 4 Digit:</dt>
                <dd class="badge badge-success">Aktif</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">Login Terakhir:</dt>
                <dd class="text-slate-700">{{ $user->last_login_at?->format('d/m/Y H:i') ?? '-' }}</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('logout') }}" class="pt-2">
            @csrf
            <button type="submit" class="btn btn-danger btn-block">
                <x-icon name="logout" class="w-4 h-4" /> Keluar dari Sesi
            </button>
        </form>
    </div>

    <!-- Edit Profile, PIN 4-Digit & Password -->
    <div class="lg:col-span-2 space-y-6">
        <!-- 1. Ubah Profil & Upload Foto -->
        <div class="glass p-6" x-data="{ previewUrl: '{{ $user->avatarUrl() ?? '' }}' }">
            <div class="orn-line mb-2"></div>
            <h3 class="text-base font-extrabold text-slate-900 mb-1">Ubah Profil & Upload Foto</h3>
            <p class="text-xs text-slate-500 mb-4">Perbarui nama lengkap, nomor handphone login, alamat email, dan foto identitas Anda.</p>

            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <!-- Avatar Upload with Live Preview -->
                <div class="flex items-center gap-4 p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                    <div class="w-16 h-16 rounded-2xl overflow-hidden bg-orange-100 border border-orange-300 flex items-center justify-center flex-none">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" alt="Preview Foto" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!previewUrl">
                            <span class="text-lg font-black text-orange-600">{{ $user->initials() }}</span>
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="label mb-1">Foto Profil (JPG/PNG/WebP, Maks 4MB)</label>
                        <input type="file" name="avatar" accept="image/*"
                               @change="if ($event.target.files[0]) previewUrl = URL.createObjectURL($event.target.files[0])"
                               class="input text-xs py-1.5">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Nama Lengkap</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="input">
                    </div>
                    <div>
                        <label class="label">Nomor Handphone / WhatsApp (Untuk Login)</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0812xxxxxxxx" class="input font-mono">
                    </div>
                </div>

                <div>
                    <label class="label">Alamat Email</label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input">
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="check" class="w-4 h-4" /> Simpan Perubahan Profil
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. Ganti PIN 4 Digit -->
        <div class="glass p-6">
            <div class="orn-line mb-2"></div>
            <div class="flex items-center justify-between gap-2 mb-1">
                <h3 class="text-base font-extrabold text-slate-900">Ganti PIN Login (4 Digit)</h3>
                <span class="badge badge-orange">Default: 1234</span>
            </div>
            <p class="text-xs text-slate-500 mb-4">Gunakan PIN 4 digit angka untuk masuk lebih cepat ke aplikasi tanpa mengetik password panjang.</p>

            <form method="POST" action="{{ route('profile.pin') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="label">PIN Saat Ini (atau Password Akun)</label>
                    <input type="password" name="current_pin" required placeholder="Masukkan PIN lama (default: 1234) atau password" class="input">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">PIN 4 Digit Baru</label>
                        <input type="password" name="pin" required inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                               placeholder="•••• (4 angka)" class="input text-center tracking-[0.5em] font-mono text-base font-extrabold">
                    </div>
                    <div>
                        <label class="label">Ulangi PIN 4 Digit Baru</label>
                        <input type="password" name="pin_confirmation" required inputmode="numeric" pattern="[0-9]{4}" maxlength="4"
                               placeholder="•••• (4 angka)" class="input text-center tracking-[0.5em] font-mono text-base font-extrabold">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="shield" class="w-4 h-4" /> Simpan PIN 4 Digit Baru
                    </button>
                </div>
            </form>
        </div>

        <!-- 3. Ganti Password Akun -->
        <div class="glass p-6">
            <div class="orn-line mb-2"></div>
            <h3 class="text-base font-extrabold text-slate-900 mb-1">Ganti Password Akun</h3>
            <p class="text-xs text-slate-500 mb-4">Ubah password akun Anda secara berkala untuk menjaga keamanan akses.</p>

            <form method="POST" action="{{ route('profile.password') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label class="label">Password (atau PIN) Saat Ini</label>
                    <input type="password" name="current_password" required placeholder="Masukkan password atau PIN saat ini" class="input">
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Password Baru (Minimal 6 Karakter)</label>
                        <input type="password" name="password" required minlength="6" placeholder="Password baru" class="input">
                    </div>
                    <div>
                        <label class="label">Konfirmasi Password Baru</label>
                        <input type="password" name="password_confirmation" required minlength="6" placeholder="Ulangi password baru" class="input">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn btn-primary">
                        <x-icon name="check" class="w-4 h-4" /> Perbarui Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
