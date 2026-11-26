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

    <!-- Recent Attendance (Integrated from HSM Portal) -->
    <div class="table-surface" x-data="{ preview: null }">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="orn-line mb-1"></div>
                <h3 class="text-sm font-extrabold text-white">10 Presensi Terakhir (Portal Absensi HSM)</h3>
            </div>
            <span class="badge badge-info">Sinkron tikismd.id</span>
        </div>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Tanggal & Kantor</th>
                        <th>Foto Selfie</th>
                        <th>Jam Masuk</th>
                        <th>Jam Pulang</th>
                        <th>Radius Kantor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($attendances as $a)
                        @php
                            $inUrl = $a->checkInPhotoUrl();
                            $outUrl = $a->checkOutPhotoUrl();
                        @endphp
                        <tr>
                            <td data-card-header="1" data-label="Tanggal & Kantor" class="text-xs">
                                <div class="font-bold text-white">{{ $a->date->format('d/m/Y') }}</div>
                                <div class="text-[11px] text-slate-500">{{ $a->officeDisplayName() }}</div>
                            </td>
                            <td data-label="Foto Selfie">
                                <div class="flex items-center gap-1.5">
                                    @if($inUrl)
                                        <button type="button"
                                                @click="preview = { url: @js($inUrl), title: 'Selfie Masuk ' + @js($a->date->format('d/m/Y')), meta: @js(($a->check_in_at?->format('H:i:s') ?? '-').' WITA • '.$a->officeDisplayName()) }"
                                                class="w-10 h-10 rounded-lg overflow-hidden border border-emerald-300 bg-slate-100 relative"
                                                aria-label="Lihat selfie masuk tanggal {{ $a->date->format('d/m/Y') }}">
                                            <img src="{{ $inUrl }}" alt="Selfie masuk" loading="lazy" class="w-full h-full object-cover">
                                            <span class="absolute bottom-0 inset-x-0 bg-slate-900/75 text-[8px] font-bold text-white text-center">IN</span>
                                        </button>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                    @if($outUrl)
                                        <button type="button"
                                                @click="preview = { url: @js($outUrl), title: 'Selfie Pulang ' + @js($a->date->format('d/m/Y')), meta: @js(($a->check_out_at?->format('H:i:s') ?? '-').' WITA • '.$a->officeDisplayName()) }"
                                                class="w-10 h-10 rounded-lg overflow-hidden border border-slate-300 bg-slate-100 relative"
                                                aria-label="Lihat selfie pulang tanggal {{ $a->date->format('d/m/Y') }}">
                                            <img src="{{ $outUrl }}" alt="Selfie pulang" loading="lazy" class="w-full h-full object-cover">
                                            <span class="absolute bottom-0 inset-x-0 bg-slate-900/75 text-[8px] font-bold text-white text-center">OUT</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                            <td data-label="Jam Masuk" class="font-mono text-xs text-white">{{ $a->check_in_at ? $a->check_in_at->format('H:i').' WITA' : '-' }}</td>
                            <td data-label="Jam Pulang" class="font-mono text-xs text-slate-300">{{ $a->check_out_at ? $a->check_out_at->format('H:i').' WITA' : '-' }}</td>
                            <td data-label="Radius Kantor" class="text-xs">
                                @if($a->hasCheckedIn())
                                    <span class="{{ $a->isWithinCheckInRadius() ? 'text-emerald-700 font-semibold' : 'text-red-700 font-semibold' }}">
                                        {{ $a->radiusSummary() }}
                                    </span>
                                @else
                                    -
                                @endif
                            </td>
                            <td data-label="Status Kehadiran">
                                <span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-slate-400 py-4">Belum ada riwayat presensi di Portal HSM.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="preview !== null" x-cloak @keydown.escape.window="preview = null"
             class="fixed inset-0 z-[9999] bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4"
             role="dialog" aria-modal="true">
            <div @click.outside="preview = null" class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden">
                <div class="p-4 border-b border-slate-200 flex items-center justify-between gap-2">
                    <div>
                        <div class="text-sm font-extrabold text-slate-900" x-text="preview?.title"></div>
                        <div class="text-xs text-slate-600" x-text="preview?.meta"></div>
                    </div>
                    <button type="button" @click="preview = null" class="btn btn-ghost btn-sm min-h-11 min-w-11 p-2" aria-label="Tutup foto">
                        <x-icon name="x" class="w-4 h-4" />
                    </button>
                </div>
                <div class="bg-slate-950 flex items-center justify-center max-h-[70dvh]">
                    <template x-if="preview">
                        <img :src="preview.url" :alt="preview.title" class="max-h-[70dvh] w-auto object-contain">
                    </template>
                </div>
            </div>
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
