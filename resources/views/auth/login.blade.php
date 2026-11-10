@extends('layouts.guest', ['title' => 'Masuk ke Sistem'])

@section('content')
<div class="w-full max-w-md space-y-6 rise" x-data="{ email: '{{ old('email', '') }}', password: '' }">
    <!-- Brand Identity -->
    <div class="text-center space-y-3">
        <div class="flex justify-center">
            <x-logo size="xl" />
        </div>
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Delivery & Pick-Up PT. HSM</h1>
            <p class="text-xs text-slate-400 mt-1">Sistem Monitoring Kinerja, Presensi GPS & Analitik Eksekutif</p>
        </div>
    </div>

    <!-- Login Card -->
    <div class="glass p-6 sm:p-8 space-y-5">
        @if(session('status'))
            <div class="p-3 rounded-xl bg-emerald-500/15 border border-emerald-500/30 text-xs text-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-3 rounded-xl bg-red-500/15 border border-red-500/35 text-xs text-red-200">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login.attempt') }}" class="space-y-4">
            @csrf
            <div>
                <label class="label" for="email">Alamat Email</label>
                <input id="email" type="email" name="email" x-model="email" required autofocus
                       placeholder="nama@hsm.id" class="input">
            </div>

            <div>
                <label class="label" for="password">Password</label>
                <input id="password" type="password" name="password" x-model="password" required
                       placeholder="••••••••" class="input">
            </div>

            <div class="flex items-center justify-between text-xs">
                <label class="inline-flex items-center gap-2 cursor-pointer text-slate-300">
                    <input type="checkbox" name="remember" value="1" class="check">
                    <span>Ingat sesi saya</span>
                </label>
                <span class="text-slate-500">Lupa password? Hubungi SuperAdmin</span>
            </div>

            <button type="submit" class="btn btn-primary btn-block py-3 text-sm">
                Masuk ke Aplikasi
            </button>
        </form>

        <div class="divider"></div>

        <!-- Quick Role Selector for Preview & Testing -->
        <div class="space-y-2">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400 text-center">
                Akses Cepat Akun Demo (Klik untuk Isi Otomatis)
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs">
                <button type="button" @click="email='superadmin@hsm.id'; password='Password123!'"
                        class="btn btn-ghost btn-sm justify-start">
                    <span class="w-2 h-2 rounded-full bg-orange-400"></span> SuperAdmin
                </button>
                <button type="button" @click="email='hrd@hsm.id'; password='Password123!'"
                        class="btn btn-ghost btn-sm justify-start">
                    <span class="w-2 h-2 rounded-full bg-sky-400"></span> Admin HRD
                </button>
                <button type="button" @click="email='freelancer@hsm.id'; password='Password123!'"
                        class="btn btn-ghost btn-sm justify-start">
                    <span class="w-2 h-2 rounded-full bg-emerald-400"></span> Karyawan
                </button>
                <button type="button" @click="email='owner@hsm.id'; password='Password123!'"
                        class="btn btn-ghost btn-sm justify-start">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span> Owner HSM
                </button>
            </div>
        </div>
    </div>

    <p class="text-center text-[11px] text-slate-500">
        &copy; {{ date('Y') }} PT HSM &mdash; PWA Mobile-First Corporate Monitoring System
    </p>
</div>
@endsection
