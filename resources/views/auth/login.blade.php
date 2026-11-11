@extends('layouts.guest', ['title' => 'Masuk ke Sistem'])

@section('content')
<div class="w-full max-w-md space-y-5 rise"
     x-data="{
         login: '{{ old('login', old('email', '')) }}',
         mode: 'pin',
         pin: '',
         password: '',
         canInstallPwa: false,
         isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
         init() {
             if (window.__hsmDeferredPrompt) {
                 this.canInstallPwa = true;
             }
             window.addEventListener('hsm-pwa-ready', () => { this.canInstallPwa = true; });
             window.addEventListener('appinstalled', () => { this.isStandalone = true; this.canInstallPwa = false; });
         },
         installApp() {
             if (window.hsmTriggerPwaInstall) {
                 window.hsmTriggerPwaInstall();
             }
         }
     }">
    <!-- Brand Identity -->
    <div class="text-center space-y-3">
        <div class="flex justify-center">
            <x-logo size="xl" />
        </div>
        <div>
            <h1 class="text-2xl font-black text-slate-900 tracking-tight">Delivery & Pick-Up PT. HSM</h1>
            <p class="text-xs text-slate-600 mt-1 font-medium">Sistem Monitoring Kinerja, Presensi GPS & Analitik Operasional</p>
        </div>
    </div>

    <!-- Tombol Install PWA jika belum terinstall -->
    <template x-if="!isStandalone">
        <div class="glass p-3.5 border border-orange-300 bg-orange-50/70 flex items-center justify-between gap-3 shadow-xs">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-9 h-9 rounded-xl bg-orange-500 text-white flex items-center justify-center flex-none shadow-xs">
                    <x-icon name="download" class="w-4 h-4" />
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-extrabold text-slate-900 truncate">Pasang Aplikasi D&P PT. HSM</div>
                    <div class="text-[11px] text-slate-600 truncate">Akses lebih cepat & notifikasi tugas langsung di HP</div>
                </div>
            </div>
            <button type="button" @click="installApp()" class="btn btn-primary btn-sm flex-none">
                Install PWA
            </button>
        </div>
    </template>

    <!-- Login Card (Paper 3D White Sheet) -->
    <div class="glass p-6 sm:p-8 space-y-5">
        @if(session('status'))
            <div class="p-3 rounded-xl bg-emerald-50 border border-emerald-300 text-xs text-emerald-900 shadow-xs">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-3 rounded-xl bg-red-50 border border-red-300 text-xs text-red-900 shadow-xs">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Mode Selector: PIN 4 Digit vs Password -->
        <div class="grid grid-cols-2 gap-1.5 p-1.5 rounded-xl bg-slate-100 border border-slate-200/80">
            <button type="button" @click="mode = 'pin'"
                    :class="mode === 'pin' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/90 font-extrabold' : 'text-slate-600 font-semibold hover:text-slate-900'"
                    class="py-2 px-3 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                <x-icon name="shield" class="w-3.5 h-3.5 text-orange-500" />
                <span>Login PIN (4 Digit)</span>
            </button>
            <button type="button" @click="mode = 'password'"
                    :class="mode === 'password' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/90 font-extrabold' : 'text-slate-600 font-semibold hover:text-slate-900'"
                    class="py-2 px-3 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                <x-icon name="badge" class="w-3.5 h-3.5 text-slate-500" />
                <span>Login Password</span>
            </button>
        </div>

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="login_mode" :value="mode">

            <div>
                <label class="label" for="login">Nomor Handphone / Email</label>
                <div class="relative">
                    <input id="login" type="text" name="login" x-model="login" required autofocus
                           placeholder="Masukkan Nomor HP (contoh: 081349497879)" class="input font-medium">
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Gunakan Nomor Handphone terdaftar untuk masuk ke sistem.</p>
            </div>

            <!-- Input PIN 4 Digit -->
            <div x-show="mode === 'pin'">
                <div class="flex items-center justify-between">
                    <label class="label" for="pin">PIN Keamanan (4 Digit)</label>
                    <span class="text-[11px] font-semibold text-orange-600">PIN Default: 1234</span>
                </div>
                <input id="pin" type="password" name="pin" x-model="pin"
                       inputmode="numeric" pattern="[0-9]*" maxlength="4"
                       :required="mode === 'pin'"
                       placeholder="•••• (4 digit angka)"
                       class="input text-center tracking-[0.6em] font-mono text-lg font-extrabold">
                <p class="text-[11px] text-slate-500 mt-1">PIN dapat diganti kapan saja pada menu Profil setelah login.</p>
            </div>

            <!-- Input Password -->
            <div x-show="mode === 'password'" x-cloak>
                <label class="label" for="password">Password Akun</label>
                <input id="password" type="password" name="password" x-model="password"
                       :required="mode === 'password'"
                       placeholder="Masukkan password akun Anda" class="input">
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="inline-flex items-center gap-2 cursor-pointer text-slate-700">
                    <input type="checkbox" name="remember" value="1" checked class="check">
                    <span>Ingat sesi saya</span>
                </label>
                <span class="text-slate-500">Lupa PIN / Password? Hubungi HRD</span>
            </div>

            <button type="submit" class="btn btn-primary btn-block py-3 text-sm shadow-md">
                Masuk ke Aplikasi
            </button>
        </form>
    </div>

    <p class="text-center text-[11px] text-slate-500">
        &copy; {{ date('Y') }} PT HSM &mdash; Delivery & Pick-Up Monitoring System
    </p>
</div>
@endsection
