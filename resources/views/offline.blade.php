@extends('layouts.guest', ['title' => 'Tidak Ada Koneksi'])

@section('content')
<div class="glass max-w-md w-full p-8 text-center space-y-4 rise">
    <div class="w-14 h-14 rounded-2xl bg-orange-500/20 border border-orange-500/40 mx-auto flex items-center justify-center text-orange-400">
        <x-icon name="pin" class="w-7 h-7" />
    </div>
    <h1 class="text-xl font-extrabold text-white">Perangkat Sedang Offline</h1>
    <p class="text-xs text-slate-300 leading-relaxed">
        Koneksi internet Anda terputus. Periksa sinyal seluler atau Wi-Fi Anda lalu muat ulang halaman untuk menyinkronkan presensi dan status pengerjaan tugas.
    </p>
    <a href="/" class="btn btn-primary btn-block">Muat Ulang Halaman</a>
</div>
@endsection
