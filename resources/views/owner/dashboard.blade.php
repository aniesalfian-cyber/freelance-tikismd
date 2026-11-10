@extends('layouts.app', ['title' => 'Executive Analytics Dashboard Owner', 'subtitle' => 'Analitik komprehensif performa karyawan, sebaran gerai, dan indeks KPI korporat'])

@section('content')
@php $c = $data['cards']; @endphp

<!-- Date Range Filter + Export Shortcut -->
<div class="glass p-4 flex flex-wrap items-center justify-between gap-3 rise">
    <form method="GET" action="{{ route('owner.dashboard') }}" class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-bold text-slate-400">Periode Analitik:</span>
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="input sm:w-40">
        <span class="text-xs text-slate-400">s/d</span>
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="input sm:w-40">
        <button type="submit" class="btn btn-primary btn-sm">Terapkan Periode</button>
    </form>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('reports.index', ['type' => 'performa', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-ghost btn-sm">
            <x-icon name="document" class="w-4 h-4" /> Buka Laporan Resmi
        </a>
        <a href="{{ route('reports.pdf', ['type' => 'performa', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}" class="btn btn-primary btn-sm">
            Ekspor PDF Corporate
        </a>
    </div>
</div>

<!-- 6 Executive KPI Summary Cards -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 rise rise-2">
    <div class="glass glass-hover p-4">
        <div class="text-[11px] font-bold uppercase text-slate-400">Karyawan Aktif</div>
        <div class="text-2xl font-extrabold text-white mt-1">{{ $c['freelancers_active'] }}</div>
        <div class="text-[11px] text-emerald-400 mt-1">{{ $c['present_today'] }} hadir hari ini</div>
    </div>
    <div class="glass glass-hover p-4">
        <div class="text-[11px] font-bold uppercase text-slate-400">Rasio Penyelesaian</div>
        <div class="text-2xl font-extrabold text-orange-400 mt-1">{{ $c['completion_rate'] }}%</div>
        <div class="text-[11px] text-slate-400 mt-1">{{ $c['completed_tasks'] }} / {{ $c['total_tasks'] }} tugas</div>
    </div>
    <div class="glass glass-hover p-4">
        <div class="text-[11px] font-bold uppercase text-slate-400">Ketepatan SLA</div>
        <div class="text-2xl font-extrabold text-emerald-400 mt-1">{{ $c['on_time_rate'] }}%</div>
        <div class="text-[11px] text-slate-400 mt-1">Tepat estimasi waktu</div>
    </div>
    <div class="glass glass-hover p-4">
        <div class="text-[11px] font-bold uppercase text-slate-400">Jam Produktif</div>
        <div class="text-2xl font-extrabold text-white mt-1">{{ $c['productive_hours'] }} <span class="text-xs font-normal">jam</span></div>
        <div class="text-[11px] text-slate-400 mt-1">Total jam kerja tercatat</div>
    </div>
    <div class="glass glass-hover p-4">
        <div class="text-[11px] font-bold uppercase text-slate-400">Rata-rata Skor KPI</div>
        <div class="text-2xl font-extrabold text-orange-300 mt-1">{{ number_format($c['avg_kpi'], 2) }}</div>
        <div class="text-[11px] text-slate-300 mt-1">Predikat Grade {{ $c['avg_grade'] }}</div>
    </div>
    <div class="glass glass-hover p-4">
        <div class="text-[11px] font-bold uppercase text-slate-400">Anomali Lokasi</div>
        <div class="text-2xl font-extrabold {{ $c['location_violations'] > 0 ? 'text-red-400' : 'text-emerald-400' }} mt-1">{{ $c['location_violations'] }}</div>
        <div class="text-[11px] text-slate-400 mt-1">Di luar radius gerai</div>
    </div>
</div>

<!-- Trend Chart & Multi-Dimensional Radar -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 rise rise-3">
    <div class="lg:col-span-2 glass p-6 space-y-3">
        <div class="orn-line mb-1"></div>
        <h2 class="text-base font-extrabold text-white">Tren Produktivitas & Rata-rata Skor KPI Harian</h2>
        <div class="h-72"><canvas id="owner-trend-chart"></canvas></div>
    </div>

    <div class="glass p-6 space-y-3">
        <div class="orn-line mb-1"></div>
        <h2 class="text-base font-extrabold text-white">Keseimbangan 4 Dimensi KPI</h2>
        <div class="h-72"><canvas id="owner-radar-chart"></canvas></div>
    </div>
</div>

<!-- Live Outlet Map & Outlet Workload Bar Chart -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 rise rise-4">
    <div class="glass p-6 space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <div class="orn-line mb-1"></div>
                <h2 class="text-base font-extrabold text-white">Peta Live Operasional Gerai HSM</h2>
            </div>
            <div class="flex items-center gap-2 text-[11px]">
                <span class="badge badge-success">Normal</span>
                <span class="badge badge-orange">Aktif</span>
                <span class="badge badge-danger">Anomali</span>
            </div>
        </div>
        <div id="owner-live-map" class="h-72 rounded-2xl overflow-hidden border border-white/10"></div>
    </div>

    <div class="glass p-6 space-y-3">
        <div class="orn-line mb-1"></div>
        <h2 class="text-base font-extrabold text-white">Distribusi Beban Tugas per Gerai</h2>
        <div class="h-72"><canvas id="owner-outlet-chart"></canvas></div>
    </div>
</div>

<!-- Leaderboards: Top Performers vs Underperformers -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="table-surface">
        <div>
            <div class="orn-line mb-1"></div>
            <h2 class="text-base font-extrabold text-white">Top 5 Karyawan Berprestasi (Skor Tertinggi)</h2>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>#</th><th>Karyawan</th><th>Tugas Selesai</th><th>Rata-rata KPI</th><th>Grade</th></tr>
                </thead>
                <tbody>
                    @forelse($data['top'] as $idx => $row)
                        <tr>
                            <td data-card-header="1" data-label="Peringkat & Karyawan">
                                <div class="flex items-center justify-between w-full gap-2">
                                    <span class="font-extrabold text-orange-400">#{{ $idx + 1 }} <span class="text-white md:hidden ml-1">{{ $row->user?->name }}</span></span>
                                    <span class="badge {{ \App\Models\PerformanceLog::badgeFor($row->grade) }} md:hidden">Grade {{ $row->grade }}</span>
                                </div>
                            </td>
                            <td data-label="Nama Karyawan" class="font-bold text-white">{{ $row->user?->name }}</td>
                            <td data-label="Tugas Selesai" class="text-xs">{{ $row->done }} / {{ $row->total }} tugas ({{ $row->days }} hari)</td>
                            <td data-label="Rata-rata Skor KPI" class="font-mono font-extrabold text-emerald-400">{{ number_format($row->avg_kpi, 2) }}</td>
                            <td data-label="Predikat"><span class="badge {{ \App\Models\PerformanceLog::badgeFor($row->grade) }}">Grade {{ $row->grade }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-400 py-4">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="table-surface">
        <div>
            <div class="orn-line mb-1"></div>
            <h2 class="text-base font-extrabold text-white">Daftar Evaluasi & Pembinaan (Skor Terendah)</h2>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>#</th><th>Karyawan</th><th>Tugas Selesai</th><th>Rata-rata KPI</th><th>Grade</th></tr>
                </thead>
                <tbody>
                    @forelse($data['bottom'] as $idx => $row)
                        <tr>
                            <td data-card-header="1" data-label="Urutan & Karyawan">
                                <div class="flex items-center justify-between w-full gap-2">
                                    <span class="font-bold text-slate-300">#{{ $idx + 1 }} <span class="text-white md:hidden ml-1">{{ $row->user?->name }}</span></span>
                                    <span class="badge {{ \App\Models\PerformanceLog::badgeFor($row->grade) }} md:hidden">Grade {{ $row->grade }}</span>
                                </div>
                            </td>
                            <td data-label="Nama Karyawan" class="font-bold text-white">{{ $row->user?->name }}</td>
                            <td data-label="Tugas Selesai" class="text-xs">{{ $row->done }} / {{ $row->total }} tugas ({{ $row->days }} hari)</td>
                            <td data-label="Rata-rata Skor KPI" class="font-mono font-extrabold text-amber-400">{{ number_format($row->avg_kpi, 2) }}</td>
                            <td data-label="Predikat"><span class="badge {{ \App\Models\PerformanceLog::badgeFor($row->grade) }}">Grade {{ $row->grade }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-slate-400 py-4">Belum ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
window.hsmReady(() => {
    const trend = @json($data['trend']);
    const radar = @json($data['radar']);
    const outletLoad = @json($data['outlet_load']);
    const mapPoints = @json($data['map']);

    if (window.Chart) {
        const trendEl = document.getElementById('owner-trend-chart');
        if (trendEl) {
            new Chart(trendEl, {
                type: 'line',
                data: {
                    labels: trend.labels,
                    datasets: [
                        {
                            label: 'Rata-rata Skor KPI',
                            data: trend.kpi,
                            borderColor: '#ff6500',
                            backgroundColor: 'rgba(255, 101, 0, 0.16)',
                            fill: true,
                            tension: 0.35,
                            spanGaps: true,
                            yAxisID: 'y',
                        },
                        {
                            label: 'Tugas Selesai',
                            data: trend.completed,
                            borderColor: '#00d2ff',
                            backgroundColor: 'rgba(0, 210, 255, 0.12)',
                            tension: 0.35,
                            yAxisID: 'y1',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { min: 0, max: 100, position: 'left', title: { display: true, text: 'Skor KPI' } },
                        y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Tugas' } }
                    }
                }
            });
        }

        const radarEl = document.getElementById('owner-radar-chart');
        if (radarEl) {
            new Chart(radarEl, {
                type: 'radar',
                data: {
                    labels: radar.labels,
                    datasets: [{
                        label: 'Skor Dimensi Tim',
                        data: radar.values,
                        borderColor: '#ff8800',
                        backgroundColor: 'rgba(255, 136, 0, 0.22)',
                        pointBackgroundColor: '#ff6500',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: { r: { min: 0, max: 100, grid: { color: 'rgba(255,255,255,0.1)' }, angleLines: { color: 'rgba(255,255,255,0.1)' }, pointLabels: { color: '#cbd5e1' } } }
                }
            });
        }

        const outletEl = document.getElementById('owner-outlet-chart');
        if (outletEl) {
            new Chart(outletEl, {
                type: 'bar',
                data: {
                    labels: outletLoad.labels,
                    datasets: [
                        { label: 'Total Ditugaskan', data: outletLoad.total, backgroundColor: 'rgba(44, 83, 138, 0.7)', borderRadius: 6 },
                        { label: 'Selesai Diproses', data: outletLoad.done, backgroundColor: '#ff6500', borderRadius: 6 }
                    ]
                },
                options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false }
            });
        }
    }

    const mapEl = document.getElementById('owner-live-map');
    if (mapEl && window.hsmCreateMap) {
        const first = mapPoints[0] || { lat: -0.502106, lng: 117.153709 };
        const map = window.hsmCreateMap(mapEl, first.lat, first.lng, 13);
        const bounds = [];
        mapPoints.forEach((p) => {
            const color = p.status === 'danger' ? '#ef4444' : (p.status === 'orange' ? '#ff6500' : '#10b981');
            L.circle([p.lat, p.lng], { radius: p.radius, color, fillColor: color, fillOpacity: 0.18, weight: 2 }).addTo(map);
            L.marker([p.lat, p.lng], { icon: window.hsmPinIcon(color) })
                .addTo(map)
                .bindPopup(`<b>${p.name}</b> (${p.code})<br>Selesai: ${p.done}/${p.tasks} tugas`);
            bounds.push([p.lat, p.lng]);
        });
        if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 15 });
    }
});
</script>
@endpush
@endsection
