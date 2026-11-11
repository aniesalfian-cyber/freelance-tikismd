@extends('layouts.guest', ['title' => 'Masuk ke Sistem'])

@section('content')
<div class="w-full max-w-sm space-y-2.5 rise"
     x-data="{
         login: '{{ old('login', old('email', '')) }}',
         mode: 'pin',
         pin: '',
         password: '',
         isStandalone: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
         init() {
             window.addEventListener('appinstalled', () => { this.isStandalone = true; });
         },
         pressDigit(d) {
             if (this.pin.length < 4) {
                 this.pin += String(d);
                 if (this.pin.length === 4 && this.login.trim().length >= 5) {
                     setTimeout(() => this.$refs.loginForm.submit(), 160);
                 }
             }
         },
         backspacePin() {
             this.pin = this.pin.slice(0, -1);
         },
         clearPin() {
             this.pin = '';
         },
         handleKeydown(e) {
             if (this.mode !== 'pin') return;
             const tag = document.activeElement?.tagName;
             if (tag === 'INPUT' || tag === 'TEXTAREA') return;
             if (/^[0-9]$/.test(e.key)) {
                 e.preventDefault();
                 this.pressDigit(e.key);
             } else if (e.key === 'Backspace') {
                 e.preventDefault();
                 this.backspacePin();
             } else if (e.key === 'Escape') {
                 e.preventDefault();
                 this.clearPin();
             }
         },
         installApp() {
             if (window.hsmTriggerPwaInstall) window.hsmTriggerPwaInstall();
         }
     }"
     @keydown.window="handleKeydown($event)">

    <!-- Compact Brand Header + Install PWA Action -->
    <div class="flex items-center justify-between gap-2 px-1">
        <div class="flex items-center gap-2.5 min-w-0">
            <x-logo size="md" />
            <div class="min-w-0">
                <h1 class="text-base font-black text-slate-900 tracking-tight leading-tight truncate">Delivery & Pick-Up PT. HSM</h1>
                <p class="text-[11px] text-slate-500 font-medium truncate">Monitoring Kinerja & Presensi GPS</p>
            </div>
        </div>
        <template x-if="!isStandalone">
            <button type="button" @click="installApp()" class="btn btn-primary btn-sm px-2.5 py-1.5 text-[11px] flex-none shadow-xs">
                <x-icon name="download" class="w-3.5 h-3.5" /> Install PWA
            </button>
        </template>
    </div>

    <!-- Login Card (Compact Paper 3D White Sheet - Minimal Vertical Scroll) -->
    <div class="glass p-4 sm:p-5 space-y-3">
        @if(session('status'))
            <div class="p-2.5 rounded-xl bg-emerald-50 border border-emerald-300 text-xs text-emerald-900">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-2.5 rounded-xl bg-red-50 border border-red-300 text-xs text-red-900">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Mode Selector: PIN 4 Digit vs Password -->
        <div class="grid grid-cols-2 gap-1 p-1 rounded-xl bg-slate-100 border border-slate-200/80">
            <button type="button" @click="mode = 'pin'"
                    :class="mode === 'pin' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/90 font-extrabold' : 'text-slate-600 font-semibold hover:text-slate-900'"
                    class="py-1.5 px-2.5 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                <x-icon name="shield" class="w-3.5 h-3.5 text-orange-500" />
                <span>Login PIN (4 Digit)</span>
            </button>
            <button type="button" @click="mode = 'password'"
                    :class="mode === 'password' ? 'bg-white text-slate-900 shadow-xs border border-slate-200/90 font-extrabold' : 'text-slate-600 font-semibold hover:text-slate-900'"
                    class="py-1.5 px-2.5 rounded-lg text-xs transition flex items-center justify-center gap-1.5">
                <x-icon name="badge" class="w-3.5 h-3.5 text-slate-500" />
                <span>Login Password</span>
            </button>
        </div>

        <form x-ref="loginForm" method="POST" action="{{ route('login.attempt') }}" class="space-y-3">
            @csrf
            <input type="hidden" name="login_mode" :value="mode">
            <input type="hidden" name="remember" value="1">

            <!-- Input Nomor Handphone / Email -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="label mb-0 text-[11px]" for="login">Nomor Handphone / Email</label>
                    <span x-show="mode === 'pin'" class="text-[10px] font-bold text-orange-600">PIN Default: 1234</span>
                </div>
                <input id="login" type="text" name="login" x-model="login" required
                       placeholder="Masukkan Nomor HP (contoh: 081349497879)" class="input py-2 text-xs font-semibold">
            </div>

            <!-- Mode 1: PIN 4 Digit + Tactile 3D Numpad -->
            <div x-show="mode === 'pin'" class="space-y-2.5">
                <input type="hidden" name="pin" :value="pin">

                <!-- 4 Visual PIN Slots -->
                <div class="flex items-center justify-center gap-3 py-0.5">
                    <template x-for="i in 4" :key="i">
                        <div class="pin-slot" :class="pin.length >= i ? 'pin-slot-filled' : ''">
                            <span x-text="pin.length >= i ? '●' : ''"></span>
                        </div>
                    </template>
                </div>

                <!-- Paper 3D Tactile Numpad Grid -->
                <div class="grid grid-cols-3 gap-2">
                    <template x-for="n in [1,2,3,4,5,6,7,8,9]" :key="n">
                        <button type="button" @click="pressDigit(n)" class="numpad-key" x-text="n"></button>
                    </template>
                    <button type="button" @click="clearPin()" class="numpad-key numpad-key-fn" title="Bersihkan PIN">
                        HAPUS
                    </button>
                    <button type="button" @click="pressDigit(0)" class="numpad-key">0</button>
                    <button type="button" @click="backspacePin()" class="numpad-key numpad-key-fn" title="Hapus 1 Angka">
                        ⌫
                    </button>
                </div>
            </div>

            <!-- Mode 2: Input Password -->
            <div x-show="mode === 'password'" x-cloak class="space-y-2">
                <div>
                    <label class="label mb-1 text-[11px]" for="password">Password Akun</label>
                    <input id="password" type="password" name="password" x-model="password"
                           :required="mode === 'password'"
                           placeholder="Masukkan password akun Anda" class="input py-2 text-xs">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block py-2.5 text-xs shadow-md">
                Masuk ke Aplikasi
            </button>
        </form>
    </div>

    <p class="text-center text-[10px] text-slate-400">
        &copy; {{ date('Y') }} PT HSM &mdash; Delivery & Pick-Up System
    </p>
</div>
@endsection
