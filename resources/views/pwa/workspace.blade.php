@extends('layouts.app', ['title' => 'Delivery & Pick-Up PT. HSM', 'dashboard_name' => 'Workspace Karyawan', 'subtitle' => 'Halo, '.auth()->user()->name])

@section('content')
<div class="max-w-xl mx-auto space-y-5">
    <!-- Courier Division & Vehicle Operational Card -->
    @php $curUser = auth()->user(); @endphp
    <div class="glass p-4 border border-slate-200 rise flex items-center justify-between gap-3 shadow-xs">
        <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="badge {{ $curUser->divisionBadge() }}">{{ $curUser->specialization }}</span>
                <span class="badge badge-muted font-bold">{{ $curUser->vehicleName() }}</span>
            </div>
            <div class="text-xs text-slate-600 font-medium truncate">
                Tugas Operasional: <b class="text-slate-900">{{ $curUser->operationalScope() }}</b>
            </div>
        </div>
        <div class="w-10 h-10 rounded-2xl bg-orange-50 border border-orange-200 flex items-center justify-center text-orange-600 flex-none shadow-xs">
            <x-icon name="pin" class="w-5 h-5" />
        </div>
    </div>

    <!-- Attendance Status Banner -->
    <div class="glass p-5 border {{ $attendance ? 'border-emerald-500/40' : 'border-orange-500/45' }} rise">
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="badge {{ $attendance ? 'badge-success' : 'badge-orange' }}">
                        {{ $attendance ? 'Presensi Terkonfirmasi' : 'Wajib Presensi Kantor Utama' }}
                    </span>
                    @if($attendance && !$attendance->check_out_at)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                            GPS Live Terhubung ke HRD
                        </span>
                    @endif
                </div>
                <h2 class="text-base font-extrabold text-white mt-1.5">
                    @if($attendance)
                        Masuk pukul {{ $attendance->check_in_at->format('H:i') }} WIB
                        @if($attendance->check_out_at)
                            &bull; Pulang {{ $attendance->check_out_at->format('H:i') }} WIB
                        @endif
                    @else
                        Anda belum melakukan presensi hadir hari ini
                    @endif
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">
                    @if($attendance && !$attendance->check_out_at)
                        Aplikasi otomatis mengirim titik lokasi Anda ke Dashboard HRD selama jam kerja aktif.
                    @else
                        Lokasi wajib: {{ $office?->name ?? 'Kantor Utama HSM' }} (Radius {{ $office?->radius_meters ?? 50 }}m)
                    @endif
                </p>
            </div>
            <a href="{{ route('pwa.attendance') }}" class="btn {{ $attendance ? 'btn-ghost' : 'btn-primary' }} btn-sm flex-none">
                <x-icon name="pin" class="w-4 h-4" /> {{ $attendance ? 'Detail GPS' : 'Presensi' }}
            </a>
        </div>
    </div>

    <!-- Daily Progress & KPI Summary -->
    <div class="grid grid-cols-2 gap-4 rise rise-2">
        <div class="glass p-4 flex items-center gap-3.5">
            @php $pct = $total ? round($done / $total * 100) : 0; @endphp
            <div class="w-14 h-14 rounded-full stat-ring p-1.5 flex-none" style="--p: {{ $pct }}">
                <div class="w-full h-full rounded-full bg-navy-800 flex items-center justify-center text-xs font-extrabold text-white">
                    {{ $pct }}%
                </div>
            </div>
            <div>
                <div class="text-[11px] font-bold uppercase text-slate-400">Progres Hari Ini</div>
                <div class="text-lg font-extrabold text-white">{{ $done }} / {{ $total }}</div>
                <div class="text-[11px] text-slate-400">Tugas Selesai</div>
            </div>
        </div>

        <a href="{{ route('pwa.performance') }}" class="glass glass-hover p-4 flex flex-col justify-between">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase text-slate-400">Skor KPI 30H</span>
                <x-icon name="star" class="w-4 h-4 text-orange-400" />
            </div>
            <div class="text-2xl font-extrabold text-orange-300 mt-1">{{ number_format($monthAvg, 2) }}</div>
            <div class="text-[11px] text-slate-400">
                Hari ini: <b class="text-white">{{ $todayLog ? number_format($todayLog->final_kpi_score, 1).' ('.$todayLog->grade.')' : 'Proses' }}</b>
            </div>
        </a>
    </div>

    <!-- Currently Active Task Banner -->
    @if($active)
        <div class="glass p-5 border border-orange-500/50 space-y-3 rise rise-3">
            <div class="flex items-center justify-between">
                <span class="badge badge-orange">Sedang Diproses</span>
                <span class="text-xs font-mono text-orange-300">Mulai {{ $active->started_at?->format('H:i') }} &bull; SLA {{ $active->estimated_duration_minutes }} mnt</span>
            </div>
            <div>
                <h3 class="text-base font-extrabold text-white">{{ $active->title }}</h3>
                <p class="text-xs text-slate-300 mt-0.5">{{ $active->outlet?->name }} &mdash; {{ $active->outlet?->address }}</p>
            </div>
            <a href="{{ route('pwa.tasks.show', $active) }}" class="btn btn-primary btn-block">
                Lanjutkan & Selesaikan Tugas Ini
            </a>
        </div>
    @endif

    <!-- Today's Task Checklist (Standalone Wide Cards, Zero Nested Cards) -->
    <div class="space-y-3 rise rise-4">
        <div class="flex items-center justify-between px-1">
            <div>
                <div class="orn-line mb-1"></div>
                <h3 class="text-base font-extrabold text-white">Daftar Tugas Hari Ini ({{ $tasks->count() }})</h3>
            </div>
            <a href="{{ route('pwa.tasks.index') }}" class="text-xs font-bold text-orange-400 hover:underline">Lihat Semua</a>
        </div>

        <div class="space-y-3">
            @forelse($tasks as $t)
                <a href="{{ route('pwa.tasks.show', $t) }}"
                   class="block glass glass-hover p-4 space-y-2 border {{ $t->status === 'in_progress' ? 'border-orange-500/45' : ($t->status === 'completed' ? 'border-emerald-500/35' : 'border-white/10') }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="badge {{ $t->statusBadge() }}">{{ $t->statusLabel() }}</span>
                                <span class="text-[11px] font-mono text-slate-400">{{ $t->startTimeShort() }} (SLA {{ $t->estimated_duration_minutes }}m)</span>
                            </div>
                            <div class="font-extrabold text-white text-sm mt-1.5">{{ $t->title }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">📍 {{ $t->outlet?->name }} &bull; {{ $t->typeLabel() }}</div>
                        </div>
                        <span class="text-xs font-bold text-orange-300 flex-none mt-1">Buka &rarr;</span>
                    </div>
                </a>
            @empty
                <div class="glass p-6 text-xs text-slate-400 text-center">
                    Tidak ada tugas yang dijadwalkan untuk Anda hari ini.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
