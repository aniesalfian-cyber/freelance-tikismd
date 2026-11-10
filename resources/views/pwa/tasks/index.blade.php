@extends('layouts.app', ['title' => 'Checklist & Daftar Tugas Saya', 'subtitle' => 'Pilih tugas untuk memperbarui status pengerjaan dan lokasi GPS'])

@section('content')
<div class="max-w-xl mx-auto space-y-4">
    <!-- Scope Tabs -->
    <div class="glass p-1.5 grid grid-cols-3 gap-1.5">
        @foreach(['today' => 'Hari Ini', 'upcoming' => 'Akan Datang', 'history' => 'Riwayat'] as $sk => $sl)
            <a href="{{ route('pwa.tasks.index', ['scope' => $sk]) }}"
               class="text-center py-2 rounded-xl text-xs font-extrabold transition {{ $scope === $sk ? 'bg-orange-500 text-white shadow' : 'text-slate-400 hover:text-white' }}">
                {{ $sl }}
            </a>
        @endforeach
    </div>

    <!-- Task Cards -->
    <div class="space-y-3">
        @forelse($tasks as $t)
            <a href="{{ route('pwa.tasks.show', $t) }}" class="block glass glass-hover p-4 space-y-2">
                <div class="flex items-center justify-between gap-2">
                    <span class="badge {{ $t->statusBadge() }}">{{ $t->statusLabel() }}</span>
                    <span class="text-xs font-mono text-slate-400">{{ $t->assigned_date->format('d/m/Y') }} &bull; {{ $t->startTimeShort() }}</span>
                </div>
                <div class="font-extrabold text-white text-base">{{ $t->title }}</div>
                <div class="flex items-center justify-between text-xs text-slate-300">
                    <span>📍 {{ $t->outlet?->name }}</span>
                    <span>⏱️ {{ $t->actual_duration_minutes !== null ? $t->actual_duration_minutes.' / '.$t->estimated_duration_minutes.' mnt' : 'SLA '.$t->estimated_duration_minutes.' mnt' }}</span>
                </div>
            </a>
        @empty
            <div class="glass p-8 text-center text-xs text-slate-400">
                Tidak ada daftar tugas pada tab ini.
            </div>
        @endforelse
    </div>

    <div>{{ $tasks->links() }}</div>
</div>
@endsection
