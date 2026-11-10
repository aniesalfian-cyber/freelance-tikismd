@extends('layouts.app', ['title' => 'Rapor & Skor Kinerja Saya', 'subtitle' => 'Evaluasi transparan indeks KPI harian dan saran peningkatan'])

@section('content')
<div class="max-w-xl mx-auto space-y-5">
    <!-- Hero Score Card -->
    <div class="glass p-6 flex items-center justify-between gap-4 rise">
        <div>
            <span class="badge {{ \App\Models\PerformanceLog::badgeFor($avgGrade) }}">
                Predikat 30 Hari: Grade {{ $avgGrade }} ({{ \App\Models\PerformanceLog::GRADE_LABELS[$avgGrade] ?? '' }})
            </span>
            <div class="text-4xl font-extrabold text-white mt-2">{{ number_format($avg, 2) }}</div>
            <div class="text-xs text-slate-400 mt-1">Rata-rata dari {{ $logs->count() }} hari kerja dinilai</div>
        </div>
        <div class="w-16 h-16 rounded-2xl bg-orange-500/20 border border-orange-500/40 flex items-center justify-center text-2xl font-extrabold text-orange-300">
            {{ $avgGrade }}
        </div>
    </div>

    <!-- Latest Day Sub-Score Breakdown -->
    @if($latest)
        <div class="glass p-5 space-y-3 rise rise-2">
            <div class="flex items-center justify-between">
                <h3 class="text-sm font-extrabold text-white">Rincian Komponen Terakhir ({{ $latest->evaluation_date->format('d M Y') }})</h3>
                <span class="font-mono text-xs font-bold text-orange-300">Skor: {{ number_format($latest->final_kpi_score, 2) }}</span>
            </div>
            @php
                $bars = [
                    ['Penyelesaian Tugas ('.round($weights[0]*100).'%)', $latest->completion_score],
                    ['Ketepatan Waktu SLA ('.round($weights[1]*100).'%)', $latest->time_score],
                    ['Kepatuhan Lokasi GPS ('.round($weights[2]*100).'%)', $latest->location_score],
                    ['Kedisiplinan Presensi ('.round($weights[3]*100).'%)', $latest->attendance_score],
                ];
            @endphp
            <div class="space-y-2.5 text-xs">
                @foreach($bars as [$bLabel, $bVal])
                    <div>
                        <div class="flex justify-between mb-1">
                            <span class="text-slate-300">{{ $bLabel }}</span>
                            <span class="font-mono font-bold text-white">{{ number_format($bVal, 1) }}</span>
                        </div>
                        <div class="h-2 rounded-full bg-white/10 overflow-hidden">
                            <div class="h-full rounded-full bg-gradient-to-r from-orange-500 to-amber-400" style="width: {{ min(100, $bVal) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Improvement Tips -->
    <div class="glass p-5 space-y-2 rise rise-3">
        <div class="orn-line mb-1"></div>
        <h3 class="text-sm font-extrabold text-white">Rekomendasi Peningkatan Performa</h3>
        <ul class="list-disc list-inside text-xs text-slate-300 space-y-1">
            @foreach($tips as $tip)
                <li>{{ $tip }}</li>
            @endforeach
        </ul>
    </div>

    <!-- 30-Day Trend Chart -->
    <div class="glass p-5 space-y-3 rise rise-4">
        <h3 class="text-sm font-extrabold text-white">Tren Nilai KPI Harian</h3>
        <div class="h-52"><canvas id="pwa-kpi-chart"></canvas></div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('pwa-kpi-chart');
    if (!el || !window.Chart) return;
    new Chart(el, {
        type: 'line',
        data: {
            labels: @json($logs->map(fn($l) => $l->evaluation_date->format('d M'))->values()),
            datasets: [{
                label: 'Skor KPI',
                data: @json($logs->pluck('final_kpi_score')->values()),
                borderColor: '#ff6500',
                backgroundColor: 'rgba(255, 101, 0, 0.2)',
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
