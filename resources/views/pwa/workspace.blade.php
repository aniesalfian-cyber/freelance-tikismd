@extends('layouts.app', ['title' => 'Delivery & Pick-Up PT. HSM', 'dashboard_name' => 'Workspace Karyawan', 'subtitle' => 'Halo, '.auth()->user()->name])

@section('content')
@php $curUser = auth()->user(); @endphp
<div class="max-w-xl mx-auto space-y-3">
    <!-- Combined Compact Division + Attendance Banner -->
    <div class="glass p-3.5 border {{ $attendance ? 'border-emerald-500/40' : 'border-orange-500/45' }} rise space-y-2.5">
        <div class="flex items-center justify-between gap-2 flex-wrap">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="badge {{ $curUser->divisionBadge() }} text-[10px]">{{ $curUser->specialization }}</span>
                <span class="badge badge-muted text-[10px] font-bold">{{ $curUser->vehicleName() }}</span>
            </div>
            @if($attendance && !$attendance->check_out_at)
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping"></span>
                    GPS Live HRD
                </span>
            @endif
        </div>

        <div class="flex items-center justify-between gap-3 pt-1 border-t border-slate-200/80">
            <div class="min-w-0">
                <h2 class="text-xs sm:text-sm font-extrabold text-white truncate">
                    @if($attendance)
                        Hadir {{ $attendance->check_in_at->format('H:i') }} WIB
                        @if($attendance->check_out_at)
                            &bull; Pulang {{ $attendance->check_out_at->format('H:i') }} WIB
                        @endif
                    @else
                        Belum Presensi Hadir Hari Ini
                    @endif
                </h2>
                <p class="text-[11px] text-slate-400 truncate">
                    {{ $office?->name ?? 'Kantor Utama HSM' }} (Radius {{ $office?->radius_meters ?? 50 }}m)
                </p>
            </div>
            <a href="{{ route('pwa.attendance') }}" class="btn {{ $attendance ? 'btn-ghost' : 'btn-primary' }} btn-sm py-1.5 px-3 text-xs flex-none">
                <x-icon name="pin" class="w-3.5 h-3.5" /> {{ $attendance ? 'GPS' : 'Presensi' }}
            </a>
        </div>
    </div>

    <!-- Compact Daily Progress & KPI Summary -->
    <div class="grid grid-cols-2 gap-3 rise rise-2">
        <div class="glass p-3 flex items-center gap-3">
            @php $pct = $total ? round($done / $total * 100) : 0; @endphp
            <div class="w-11 h-11 rounded-full stat-ring p-1 flex-none" style="--p: {{ $pct }}">
                <div class="w-full h-full rounded-full bg-navy-800 flex items-center justify-center text-[11px] font-extrabold text-white">
                    {{ $pct }}%
                </div>
            </div>
            <div class="min-w-0">
                <div class="text-[10px] font-bold uppercase text-slate-400">Progres Tugas</div>
                <div class="text-base font-extrabold text-white leading-tight">{{ $done }} / {{ $total }}</div>
                <div class="text-[10px] text-slate-400">Selesai Hari Ini</div>
            </div>
        </div>

        <a href="{{ route('pwa.performance') }}" class="glass glass-hover p-3 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase text-slate-400">Skor KPI 30H</span>
                <x-icon name="star" class="w-3.5 h-3.5 text-orange-400" />
            </div>
            <div class="text-xl font-extrabold text-orange-300 leading-tight">{{ number_format($monthAvg, 2) }}</div>
            <div class="text-[10px] text-slate-400 truncate">
                Hari ini: <b class="text-white">{{ $todayLog ? number_format($todayLog->final_kpi_score, 1).' ('.$todayLog->grade.')' : 'Proses' }}</b>
            </div>
        </a>
    </div>

    <!-- Currently Active Task Banner -->
    @if($active)
        <div class="glass p-3.5 border border-orange-500/50 space-y-2 rise rise-3">
            <div class="flex items-center justify-between">
                <span class="badge badge-orange text-[10px]">Sedang Diproses</span>
                <span class="text-[11px] font-mono text-orange-300">Mulai {{ $active->started_at?->format('H:i') }} &bull; SLA {{ $active->estimated_duration_minutes }}m</span>
            </div>
            <div class="flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <h3 class="text-xs sm:text-sm font-extrabold text-white truncate">{{ $active->title }}</h3>
                    <p class="text-[11px] text-slate-300 truncate">{{ $active->outlet?->name }}</p>
                </div>
                <a href="{{ route('pwa.tasks.show', $active) }}" class="btn btn-primary btn-sm flex-none text-xs">
                    Selesaikan &rarr;
                </a>
            </div>
        </div>
    @endif

    <!-- Today's Task Checklist (Compact Scrollable Area to Prevent Long Page Scroll) -->
    <div class="space-y-2 rise rise-4">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-xs sm:text-sm font-extrabold text-white">Daftar Tugas Hari Ini ({{ $tasks->count() }})</h3>
            <a href="{{ route('pwa.tasks.index') }}" class="text-[11px] font-bold text-orange-400 hover:underline">Lihat Semua</a>
        </div>

        <div class="space-y-2 max-h-[42dvh] overflow-y-auto pr-0.5">
            @forelse($tasks as $t)
                <a href="{{ route('pwa.tasks.show', $t) }}"
                   class="block glass glass-hover p-3 border {{ $t->status === 'in_progress' ? 'border-orange-500/45' : ($t->status === 'completed' ? 'border-emerald-500/35' : 'border-white/10') }}">
                    <div class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="badge {{ $t->statusBadge() }} text-[10px]">{{ $t->statusLabel() }}</span>
                                <span class="text-[10px] font-mono text-slate-400">{{ $t->startTimeShort() }} ({{ $t->estimated_duration_minutes }}m)</span>
                            </div>
                            <div class="font-extrabold text-white text-xs sm:text-sm truncate mt-1">{{ $t->title }}</div>
                            <div class="text-[11px] text-slate-400 truncate">📍 {{ $t->outlet?->name }}</div>
                        </div>
                        <span class="text-xs font-bold text-orange-300 flex-none">Buka &rarr;</span>
                    </div>
                </a>
            @empty
                <div class="glass p-4 text-xs text-slate-400 text-center">
                    Tidak ada tugas yang dijadwalkan untuk Anda hari ini.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
