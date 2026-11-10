@extends('layouts.app', ['title' => 'Dashboard Operasional HRD & Supervisor', 'subtitle' => 'Monitoring real-time kehadiran, distribusi tugas, dan status gerai hari ini'])

@section('content')
<!-- Top Operational KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 rise">
    <div class="glass glass-hover p-5">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Kehadiran Hari Ini</div>
        <div class="mt-2 flex items-baseline gap-2">
            <span class="text-3xl font-extrabold text-emerald-400">{{ $today['present'] }}</span>
            <span class="text-xs text-slate-400">/ {{ $today['freelancers_total'] }} karyawan</span>
        </div>
        <div class="mt-1 text-xs text-amber-400">{{ $today['late_attendance'] }} hadir terlambat</div>
    </div>

    <div class="glass glass-hover p-5">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Sedang Diproses</div>
        <div class="mt-2 text-3xl font-extrabold text-orange-400">{{ $today['task_counts']['in_progress'] }}</div>
        <div class="mt-1 text-xs text-slate-400">Dari {{ $today['task_counts']['total'] }} tugas hari ini</div>
    </div>

    <div class="glass glass-hover p-5">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Selesai Diproses</div>
        <div class="mt-2 text-3xl font-extrabold text-white">{{ $today['task_counts']['completed'] }}</div>
        <div class="mt-1 text-xs text-emerald-400">
            {{ $today['task_counts']['total'] ? round($today['task_counts']['completed'] / $today['task_counts']['total'] * 100) : 0 }}% penyelesaian hari ini
        </div>
    </div>

    <div class="glass glass-hover p-5">
        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Melewati Jam Mulai</div>
        <div class="mt-2 text-3xl font-extrabold text-red-400">{{ $today['late_tasks'] }}</div>
        <div class="mt-1 text-xs text-slate-400">{{ $today['task_counts']['pending'] }} total belum diproses</div>
    </div>
</div>

<!-- Quick Actions Bar -->
<div class="glass p-4 flex flex-wrap items-center justify-between gap-3 rise rise-2">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('hrd.tasks.create') }}" class="btn btn-primary btn-sm">
            <x-icon name="plus" class="w-4 h-4" /> Beri Tugas Baru
        </a>
        <form method="POST" action="{{ route('hrd.tasks.generate') }}" class="inline">
            @csrf
            <input type="hidden" name="date" value="{{ today()->toDateString() }}">
            <button type="submit" class="btn btn-ghost btn-sm">
                <x-icon name="repeat" class="w-4 h-4" /> Generate Tugas Pasti Hari Ini
            </button>
        </form>
        <a href="{{ route('hrd.freelancers.create') }}" class="btn btn-ghost btn-sm">+ Tambah Karyawan</a>
        <a href="{{ route('hrd.outlets.create') }}" class="btn btn-ghost btn-sm">+ Tambah Gerai</a>
    </div>
    <a href="{{ route('reports.index', ['type' => 'performa']) }}" class="btn btn-ghost btn-sm">
        <x-icon name="document" class="w-4 h-4" /> Cetak Laporan Corporate
    </a>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 rise rise-3">
    <!-- Live Outlet Map -->
    <div class="lg:col-span-2 glass p-5 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <div class="orn-line mb-1"></div>
                <h2 class="text-base font-extrabold text-white">Peta Live Gerai & Titik Pengerjaan</h2>
            </div>
            <div class="flex items-center gap-3 text-[11px]">
                <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Normal</span>
                <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-orange-400"></span> Aktif</span>
                <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-red-400"></span> Terlambat</span>
            </div>
        </div>
        <div id="hrd-live-map" class="h-80 rounded-2xl overflow-hidden border border-white/10"></div>
    </div>

    <!-- Absent Today & Late Tasks -->
    <div class="space-y-6">
        <div class="glass p-5 space-y-3">
            <div class="orn-line mb-1"></div>
            <h2 class="text-base font-extrabold text-white">Belum Presensi Hari Ini ({{ $today['absent']->count() }})</h2>
            <div class="divide-y divide-white/10 max-h-44 overflow-y-auto">
                @forelse($today['absent'] as $ab)
                    <div class="flex items-center justify-between py-2.5 text-xs">
                        <div>
                            <div class="font-bold text-white">{{ $ab->name }}</div>
                            <div class="text-slate-400">{{ $ab->phone ?: $ab->email }}</div>
                        </div>
                        <a href="{{ route('hrd.attendances.index') }}" class="badge badge-warning">Input Manual</a>
                    </div>
                @empty
                    <p class="text-xs text-emerald-400 py-3 text-center">Semua karyawan aktif sudah presensi!</p>
                @endforelse
            </div>
        </div>

        <div class="glass p-5 space-y-3">
            <div class="orn-line mb-1"></div>
            <h2 class="text-base font-extrabold text-white">Tugas Sedang Dikerjakan ({{ $activeTasks->count() }})</h2>
            <div class="divide-y divide-white/10 max-h-48 overflow-y-auto">
                @forelse($activeTasks as $at)
                    <a href="{{ route('hrd.tasks.show', $at) }}" class="block py-2.5 text-xs hover:text-orange-300 transition">
                        <div class="flex justify-between font-bold text-white">
                            <span>{{ $at->title }}</span>
                            <span class="text-orange-300 font-mono">{{ $at->started_at?->format('H:i') }}</span>
                        </div>
                        <div class="text-slate-400 mt-0.5">{{ $at->user?->name }} &bull; {{ $at->outlet?->name }}</div>
                    </a>
                @empty
                    <p class="text-xs text-slate-400 py-3 text-center">Tidak ada tugas yang sedang berjalan saat ini.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.hsmReady(() => {
    const points = @json($map);
    const el = document.getElementById('hrd-live-map');
    if (!el || !window.hsmCreateMap) return;
    const center = points[0] || { lat: -0.502106, lng: 117.153709 };
    const map = window.hsmCreateMap(el, center.lat, center.lng, 13);
    const bounds = [];
    points.forEach((p) => {
        const color = p.status === 'danger' ? '#ef4444' : (p.status === 'orange' ? '#ff6500' : '#10b981');
        L.circle([p.lat, p.lng], { radius: p.radius, color, fillColor: color, fillOpacity: 0.18, weight: 2 }).addTo(map);
        L.marker([p.lat, p.lng], { icon: window.hsmPinIcon(color) })
            .addTo(map)
            .bindPopup(`<b>${p.name}</b> (${p.code})<br>Tugas Selesai: ${p.done}/${p.tasks}<br>Radius: ${p.radius} m`);
        bounds.push([p.lat, p.lng]);
    });
    if (bounds.length > 1) map.fitBounds(bounds, { padding: [32, 32], maxZoom: 15 });
});
</script>
@endpush
@endsection
