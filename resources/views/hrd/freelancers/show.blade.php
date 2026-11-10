@extends('layouts.app', ['title' => 'Profil & Riwayat Performa: '.$freelancer->name, 'subtitle' => 'NIK: '.($freelancer->nik ?? '-').' • '.$freelancer->email])

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Card -->
    <div class="glass p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <span class="badge {{ $freelancer->is_active ? 'badge-success' : 'badge-danger' }}">
                    {{ $freelancer->is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
                <h2 class="text-xl font-extrabold text-white mt-2">{{ $freelancer->name }}</h2>
                <p class="text-xs text-slate-400">{{ $freelancer->specialization ?: 'Operasional Gerai' }}</p>
            </div>
            <div class="text-right">
                <div class="text-xs text-slate-400">Rata-rata KPI</div>
                <div class="text-2xl font-extrabold text-orange-400">{{ number_format($avgKpi, 2) }}</div>
            </div>
        </div>

        <div class="divider"></div>
        <dl class="space-y-2 text-xs">
            <div class="flex justify-between"><dt class="text-slate-400">NIK:</dt><dd class="font-mono text-white">{{ $freelancer->nik }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">WhatsApp:</dt><dd class="text-white">{{ $freelancer->phone }}</dd></div>
            <div class="flex justify-between"><dt class="text-slate-400">Total Tugas Selesai:</dt><dd class="font-bold text-emerald-400">{{ $taskStats['done'] }} / {{ $taskStats['total'] }}</dd></div>
        </dl>

        <div class="flex flex-wrap gap-2 pt-2">
            <a href="{{ route('hrd.freelancers.edit', $freelancer) }}" class="btn btn-ghost btn-sm">Edit Data</a>
            <form method="POST" action="{{ route('hrd.freelancers.toggle', $freelancer) }}">
                @csrf
                <button type="submit" class="btn btn-ghost btn-sm">{{ $freelancer->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
            </form>
            <a href="{{ route('reports.index', ['type' => 'performa', 'user_id' => $freelancer->id]) }}" class="btn btn-primary btn-sm">Laporan Individu</a>
        </div>
    </div>

    <!-- KPI Trend Chart -->
    <div class="lg:col-span-2 glass p-6 space-y-3">
        <div class="orn-line mb-1"></div>
        <h3 class="text-base font-extrabold text-white">Grafik Tren Skor KPI (30 Hari Terakhir)</h3>
        <div class="h-60"><canvas id="fl-kpi-chart"></canvas></div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Tasks -->
    <div class="table-surface">
        <div>
            <div class="orn-line mb-1"></div>
            <h3 class="text-sm font-extrabold text-white">10 Penugasan Terakhir</h3>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Tanggal</th><th>Tugas & Gerai</th><th>Durasi</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse($tasks as $t)
                        <tr>
                            <td data-card-header="1" data-label="Tanggal & Nomor Tugas" class="text-xs">
                                <span class="font-mono font-bold text-orange-300">{{ $t->task_number }}</span> &bull; {{ $t->assigned_date->format('d/m/Y') }}
                            </td>
                            <td data-label="Tugas & Lokasi Gerai">
                                <div>
                                    <a href="{{ route('hrd.tasks.show', $t) }}" class="font-bold text-white hover:text-orange-300">{{ $t->title }}</a>
                                    <div class="text-xs text-slate-400">{{ $t->outlet?->name }}</div>
                                </div>
                            </td>
                            <td data-label="Durasi Pengerjaan" class="text-xs">{{ $t->actual_duration_minutes ?? '-' }} / {{ $t->estimated_duration_minutes }} mnt</td>
                            <td data-label="Status"><span class="badge {{ $t->statusBadge() }}">{{ $t->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-400 py-4">Belum ada tugas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Attendance -->
    <div class="table-surface">
        <div>
            <div class="orn-line mb-1"></div>
            <h3 class="text-sm font-extrabold text-white">10 Presensi Terakhir</h3>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Tanggal</th><th>Jam Masuk</th><th>Jam Pulang</th><th>Jarak</th><th>Status</th></tr>
                </thead>
                <tbody>
                    @forelse($attendances as $a)
                        <tr>
                            <td data-card-header="1" data-label="Tanggal Presensi" class="text-xs font-bold text-white">{{ $a->date->format('d/m/Y') }}</td>
                            <td data-label="Jam Masuk" class="font-mono text-xs text-white">{{ $a->check_in_at->format('H:i') }} WITA</td>
                            <td data-label="Jam Pulang" class="font-mono text-xs text-slate-300">{{ $a->check_out_at?->format('H:i') ?? '-' }}</td>
                            <td data-label="Jarak ke Kantor Utama" class="text-xs">{{ $a->check_in_distance_meters }} m</td>
                            <td data-label="Status Kehadiran"><span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-400 py-4">Belum ada presensi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('fl-kpi-chart');
    if (!ctx || !window.Chart) return;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($logs->map(fn($l) => $l->evaluation_date->format('d M'))->values()),
            datasets: [{
                label: 'Skor KPI Harian',
                data: @json($logs->pluck('final_kpi_score')->values()),
                borderColor: '#ff6500',
                backgroundColor: 'rgba(255, 101, 0, 0.18)',
                fill: true,
                tension: 0.35,
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, scales: { y: { min: 0, max: 100 } } }
    });
});
</script>
@endpush
@endsection
