@extends('layouts.app', ['title' => 'Profil & Keamanan Akun', 'subtitle' => 'Kelola foto profil, identitas, PIN 4 digit, dan password Anda'])

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 rise"
     x-data="{
         tab: '{{ $errors->has('current_pin') || $errors->has('pin') ? 'pin' : ($errors->has('current_password') || $errors->has('password') ? 'password' : 'profile') }}',
         previewUrl: '{{ $user->avatarUrl() ?? '' }}',
         activePinField: 'current_pin',
         current_pin: '',
         pin: '',
         pin_confirmation: '',
         pressPinDigit(d) {
             const f = this.activePinField;
             if (f === 'current_pin') {
                 if (this.current_pin.length < 16) this.current_pin += String(d);
                 if (this.current_pin.length === 4) this.activePinField = 'pin';
             } else if (f === 'pin') {
                 if (this.pin.length < 4) this.pin += String(d);
                 if (this.pin.length === 4) this.activePinField = 'pin_confirmation';
             } else if (f === 'pin_confirmation') {
                 if (this.pin_confirmation.length < 4) this.pin_confirmation += String(d);
             }
         },
         backspacePinField() {
             const f = this.activePinField;
             this[f] = (this[f] || '').slice(0, -1);
         },
         clearPinField() {
             const f = this.activePinField;
             this[f] = '';
         }
     }">

    <!-- Compact Summary Card -->
    <div class="glass p-4 space-y-3 h-fit">
        <div class="flex items-center gap-3">
            @if($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="w-13 h-13 rounded-2xl object-cover border-2 border-orange-500/50 shadow-xs">
            @else
                <div class="w-13 h-13 rounded-2xl bg-orange-500 text-white border border-orange-600 flex items-center justify-center text-base font-black shadow-xs">
                    {{ $user->initials() }}
                </div>
            @endif
            <div class="min-w-0">
                <span class="badge badge-orange text-[10px]">{{ $user->roleLabel() }}</span>
                <h2 class="text-sm font-extrabold text-slate-900 truncate mt-0.5">{{ $user->name }}</h2>
                <p class="text-[11px] text-slate-500 truncate">{{ $user->phone ?: $user->email }}</p>
            </div>
        </div>
        <div class="divider"></div>
        <dl class="space-y-1.5 text-xs">
            <div class="flex justify-between">
                <dt class="text-slate-500">NIK:</dt>
                <dd class="font-mono font-bold text-slate-900">{{ $user->nik ?? '-' }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-500">No. Handphone:</dt>
                <dd class="font-bold text-slate-900">{{ $user->phone ?? '-' }}</dd>
            </div>
            @if($user->isFreelancer())
                <div class="flex justify-between">
                    <dt class="text-slate-500">Divisi:</dt>
                    <dd class="font-bold text-slate-900 truncate max-w-44">{{ $user->specialization ?? 'Kurir' }}</dd>
                </div>
            @endif
            <div class="flex justify-between">
                <dt class="text-slate-500">Status PIN 4 Digit:</dt>
                <dd class="badge badge-success text-[10px]">Aktif</dd>
            </div>
        </dl>

        <form method="POST" action="{{ route('logout') }}" class="pt-1">
            @csrf
            <button type="submit" class="btn btn-danger btn-sm btn-block">
                <x-icon name="logout" class="w-3.5 h-3.5" /> Keluar dari Sesi
            </button>
        </form>
    </div>

    <!-- Right Panel: Tabbed Forms (Zero Vertical Scroll) -->
    <div class="lg:col-span-2 glass p-4 sm:p-5 space-y-4 h-fit">
        <!-- Tab Switcher -->
        <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-slate-100 border border-slate-200">
            <button type="button" @click="tab = 'profile'"
                    :class="tab === 'profile' ? 'bg-white text-slate-900 shadow-xs border border-slate-200 font-extrabold' : 'text-slate-600 font-semibold'"
                    class="py-1.5 px-2 rounded-lg text-xs transition text-center">
                Profil & Foto
            </button>
            <button type="button" @click="tab = 'pin'"
                    :class="tab === 'pin' ? 'bg-white text-slate-900 shadow-xs border border-slate-200 font-extrabold' : 'text-slate-600 font-semibold'"
                    class="py-1.5 px-2 rounded-lg text-xs transition text-center">
                Ganti PIN (Numpad)
            </button>
            <button type="button" @click="tab = 'password'"
                    :class="tab === 'password' ? 'bg-white text-slate-900 shadow-xs border border-slate-200 font-extrabold' : 'text-slate-600 font-semibold'"
                    class="py-1.5 px-2 rounded-lg text-xs transition text-center">
                Ganti Password
            </button>
        </div>

        <!-- TAB 1: Ubah Profil & Upload Foto -->
        <div x-show="tab === 'profile'" class="space-y-3">
            <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                @method('PUT')

                <div class="flex items-center gap-3 p-3 rounded-xl bg-slate-50 border border-slate-200">
                    <div class="w-13 h-13 rounded-xl overflow-hidden bg-orange-100 border border-orange-300 flex items-center justify-center flex-none">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" alt="Preview Foto" class="w-full h-full object-cover">
                        </template>
                        <template x-if="!previewUrl">
                            <span class="text-sm font-black text-orange-600">{{ $user->initials() }}</span>
                        </template>
                    </div>
                    <div class="flex-1 min-w-0">
                        <label class="label mb-0.5 text-[11px]">Upload Foto Profil (JPG/PNG/WebP)</label>
                        <input type="file" name="avatar" accept="image/*"
                               @change="if ($event.target.files[0]) previewUrl = URL.createObjectURL($event.target.files[0])"
                               class="input text-xs py-1">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="label text-[11px]">Nama Lengkap</label>
                        <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="input py-2 text-xs">
                    </div>
                    <div>
                        <label class="label text-[11px]">Nomor HP Login</label>
                        <input type="text" name="phone" value="{{ old('phone', $user->phone) }}" placeholder="0812xxxxxxxx" class="input py-2 text-xs font-mono">
                    </div>
                    <div>
                        <label class="label text-[11px]">Alamat Email</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="input py-2 text-xs">
                    </div>
                </div>

                <div class="flex justify-end pt-1">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <x-icon name="check" class="w-4 h-4" /> Simpan Perubahan Profil
                    </button>
                </div>
            </form>
        </div>

        <!-- TAB 2: Ganti PIN 4 Digit dengan Numpad Interaktif -->
        <div x-show="tab === 'pin'" x-cloak>
            <form method="POST" action="{{ route('profile.pin') }}" class="grid grid-cols-1 md:grid-cols-2 gap-4 items-center">
                @csrf
                @method('PUT')

                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-extrabold text-slate-900">Kolom Input PIN</span>
                        <span class="badge badge-orange text-[10px]">Default: 1234</span>
                    </div>

                    <div @click="activePinField = 'current_pin'"
                         class="p-2.5 rounded-xl border transition cursor-pointer"
                         :class="activePinField === 'current_pin' ? 'border-orange-500 bg-orange-50/40' : 'border-slate-200 bg-slate-50'">
                        <label class="text-[10px] font-bold uppercase text-slate-500 block">1. PIN Lama (atau Password)</label>
                        <input type="password" name="current_pin" x-model="current_pin" @focus="activePinField = 'current_pin'"
                               required placeholder="Ketuk di sini lalu tekan Numpad"
                               class="w-full bg-transparent text-sm font-mono font-extrabold text-slate-900 focus:outline-none tracking-widest mt-0.5">
                    </div>

                    <div @click="activePinField = 'pin'"
                         class="p-2.5 rounded-xl border transition cursor-pointer"
                         :class="activePinField === 'pin' ? 'border-orange-500 bg-orange-50/40' : 'border-slate-200 bg-slate-50'">
                        <label class="text-[10px] font-bold uppercase text-slate-500 block">2. PIN 4 Digit Baru</label>
                        <input type="password" name="pin" x-model="pin" @focus="activePinField = 'pin'"
                               required maxlength="4" pattern="[0-9]{4}" placeholder="•••• (4 digit angka)"
                               class="w-full bg-transparent text-sm font-mono font-extrabold text-slate-900 focus:outline-none tracking-[0.5em] mt-0.5">
                    </div>

                    <div @click="activePinField = 'pin_confirmation'"
                         class="p-2.5 rounded-xl border transition cursor-pointer"
                         :class="activePinField === 'pin_confirmation' ? 'border-orange-500 bg-orange-50/40' : 'border-slate-200 bg-slate-50'">
                        <label class="text-[10px] font-bold uppercase text-slate-500 block">3. Ulangi PIN 4 Digit Baru</label>
                        <input type="password" name="pin_confirmation" x-model="pin_confirmation" @focus="activePinField = 'pin_confirmation'"
                               required maxlength="4" pattern="[0-9]{4}" placeholder="•••• (4 digit angka)"
                               class="w-full bg-transparent text-sm font-mono font-extrabold text-slate-900 focus:outline-none tracking-[0.5em] mt-0.5">
                    </div>

                    <button type="submit" class="btn btn-primary btn-block py-2 text-xs">
                        <x-icon name="shield" class="w-4 h-4" /> Simpan PIN 4 Digit Baru
                    </button>
                </div>

                <!-- Numpad 3D -->
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="n in [1,2,3,4,5,6,7,8,9]" :key="n">
                        <button type="button" @click="pressPinDigit(n)" class="numpad-key" x-text="n"></button>
                    </template>
                    <button type="button" @click="clearPinField()" class="numpad-key numpad-key-fn">HAPUS</button>
                    <button type="button" @click="pressPinDigit(0)" class="numpad-key">0</button>
                    <button type="button" @click="backspacePinField()" class="numpad-key numpad-key-fn">⌫</button>
                </div>
            </form>
        </div>

        <!-- TAB 3: Ganti Password Akun -->
        <div x-show="tab === 'password'" x-cloak>
            <form method="POST" action="{{ route('profile.password') }}" class="space-y-3">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="label text-[11px]">Password / PIN Saat Ini</label>
                        <input type="password" name="current_password" required placeholder="Password / PIN lama" class="input py-2 text-xs">
                    </div>
                    <div>
                        <label class="label text-[11px]">Password Baru (Min. 6 Karakter)</label>
                        <input type="password" name="password" required minlength="6" placeholder="Password baru" class="input py-2 text-xs">
                    </div>
                    <div>
                        <label class="label text-[11px]">Ulangi Password Baru</label>
                        <input type="password" name="password_confirmation" required minlength="6" placeholder="Konfirmasi password" class="input py-2 text-xs">
                    </div>
                </div>
                <div class="flex justify-end pt-1">
                    <button type="submit" class="btn btn-primary btn-sm">
                        <x-icon name="check" class="w-4 h-4" /> Perbarui Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
