@extends('layouts.guest', ['title' => 'Masuk ke Sistem'])

@section('content')
<div class="w-full max-w-md space-y-6 rise" x-data="{ login: '{{ old('login', old('email', '')) }}', password: '' }">
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

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf
            <div>
                <label class="label" for="login">Nomor Handphone / Email</label>
                <div class="relative">
                    <input id="login" type="text" name="login" x-model="login" required autofocus
                           placeholder="Masukkan Nomor HP (contoh: 081349497879)" class="input pl-10 font-medium">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <x-icon name="phone" class="w-4 h-4 text-orange-500" />
                    </div>
                </div>
                <p class="text-[11px] text-slate-500 mt-1">Kurir & staf masuk langsung menggunakan Nomor Handphone terdaftar.</p>
            </div>

            <div>
                <label class="label" for="password">Password</label>
                <input id="password" type="password" name="password" x-model="password" required
                       placeholder="Default: 123456" class="input">
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="inline-flex items-center gap-2 cursor-pointer text-slate-700">
                    <input type="checkbox" name="remember" value="1" class="check">
                    <span>Ingat sesi saya</span>
                </label>
                <span class="text-slate-500">Lupa password? Hubungi HRD</span>
            </div>

            <button type="submit" class="btn btn-primary btn-block py-3 text-sm shadow-md">
                Masuk ke Aplikasi
            </button>
        </form>

        <div class="divider"></div>

        <!-- Quick Access for Testing Real Accounts -->
        <div class="space-y-2">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 text-center">
                Pintasan Masuk Cepat Akun Real (Password: 123456)
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <button type="button" @click="login='081349497879'; password='123456'"
                        class="btn btn-ghost btn-sm justify-start text-left" title="Dedy Endra (R4 Kurier Delivery)">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 flex-none"></span>
                    <span class="truncate text-slate-800">Kurir: Dedy Endra</span>
                </button>
                <button type="button" @click="login='085250815716'; password='123456'"
                        class="btn btn-ghost btn-sm justify-start text-left" title="Arif (Admin HRD)">
                    <span class="w-2 h-2 rounded-full bg-sky-500 flex-none"></span>
                    <span class="truncate text-slate-800">HRD: Arif</span>
                </button>
                <button type="button" @click="login='08115557089'; password='123456'"
                        class="btn btn-ghost btn-sm justify-start text-left" title="Bpk. Olly (Owner)">
                    <span class="w-2 h-2 rounded-full bg-amber-500 flex-none"></span>
                    <span class="truncate text-slate-800">Owner: Bpk. Olly</span>
                </button>
                <button type="button" @click="login='superadmin@hsm.id'; password='123456'"
                        class="btn btn-ghost btn-sm justify-start text-left" title="Super Administrator">
                    <span class="w-2 h-2 rounded-full bg-orange-500 flex-none"></span>
                    <span class="truncate text-slate-800">SuperAdmin</span>
                </button>
            </div>
        </div>
    </div>

    <p class="text-center text-[11px] text-slate-500">
        &copy; {{ date('Y') }} PT HSM &mdash; Delivery & Pick-Up Monitoring System
    </p>
</div>
@endsection
