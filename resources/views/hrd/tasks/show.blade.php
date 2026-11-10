@extends('layouts.app', ['title' => 'Detail & Telemetri Tugas: '.$task->task_number, 'subtitle' => $task->title])

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Details -->
    <div class="lg:col-span-2 space-y-6">
        <div class="glass p-6 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="badge {{ $task->statusBadge() }}">{{ $task->statusLabel() }}</span>
                        <span class="badge {{ $task->task_type === 'pasti_harian' ? 'badge-info' : 'badge-orange' }}">{{ $task->typeLabel() }}</span>
                    </div>
                    <h2 class="text-xl font-extrabold text-white mt-2">{{ $task->title }}</h2>
                    <p class="text-xs text-slate-300 mt-1">{{ $task->description ?: 'Tidak ada deskripsi tambahan.' }}</p>
                </div>
                <div class="text-right text-xs">
                    <div class="text-slate-400">Tanggal Tugas</div>
                    <div class="font-bold text-white">{{ $task->assigned_date->format('d F Y') }}</div>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 border-t border-b border-white/10 py-4 text-xs">
                <div>
                    <div class="text-slate-400">Karyawan</div>
                    <div class="font-bold text-white mt-0.5">{{ $task->user?->name }}</div>
                </div>
                <div>
                    <div class="text-slate-400">Gerai Lokasi</div>
                    <div class="font-bold text-white mt-0.5">{{ $task->outlet?->name }}</div>
                </div>
                <div>
                    <div class="text-slate-400">Target Mulai / SLA</div>
                    <div class="font-bold text-orange-300 mt-0.5">{{ $task->startTimeShort() }} ({{ $task->estimated_duration_minutes }} mnt)</div>
                </div>
                <div>
                    <div class="text-slate-400">Durasi Aktual</div>
                    <div class="font-bold text-emerald-300 mt-0.5">{{ $task->actual_duration_minutes !== null ? $task->actual_duration_minutes.' menit' : 'Belum selesai' }}</div>
                </div>
            </div>

            <!-- Timestamps & GPS Distances -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
                <div class="space-y-1.5">
                    <div class="font-bold text-orange-300 uppercase">Telemetri Mulai Pengerjaan</div>
                    <div>Waktu Mulai: <b class="text-white">{{ $task->started_at?->format('d/m/Y H:i:s') ?? '-' }}</b></div>
                    <div>Koordinat: <span class="font-mono text-slate-300">{{ $task->start_latitude ? $task->start_latitude.', '.$task->start_longitude : '-' }}</span></div>
                    <div>Jarak ke Gerai: <b class="text-white">{{ $task->start_distance_meters !== null ? $task->start_distance_meters.' meter' : '-' }}</b></div>
                </div>
                <div class="space-y-1.5 sm:border-l sm:border-white/10 sm:pl-6">
                    <div class="font-bold text-emerald-300 uppercase">Telemetri Selesai Pengerjaan</div>
                    <div>Waktu Selesai: <b class="text-white">{{ $task->completed_at?->format('d/m/Y H:i:s') ?? '-' }}</b></div>
                    <div>Koordinat: <span class="font-mono text-slate-300">{{ $task->end_latitude ? $task->end_latitude.', '.$task->end_longitude : '-' }}</span></div>
                    <div>Jarak ke Gerai: <b class="text-white">{{ $task->end_distance_meters !== null ? $task->end_distance_meters.' meter' : '-' }}</b></div>
                </div>
            </div>

            @if($task->notes)
                <div class="border-t border-white/10 pt-4 text-xs space-y-1">
                    <div class="font-bold text-white">Catatan Pengerjaan Karyawan:</div>
                    <p class="text-slate-300 leading-relaxed">{{ $task->notes }}</p>
                </div>
            @endif

            @if($task->proofUrl())
                <div class="space-y-2">
                    <div class="text-xs font-bold text-white">Foto Bukti Pengerjaan:</div>
                    <img src="{{ $task->proofUrl() }}" alt="Bukti Tugas" class="rounded-2xl max-h-80 border border-white/15">
                </div>
            @endif
        </div>
    </div>

    <!-- Right Map & Admin Actions -->
    <div class="space-y-6">
        <div class="glass p-5 space-y-3">
            <h3 class="text-sm font-extrabold text-white">Peta Verifikasi Lokasi Tugas</h3>
            <div id="task-detail-map" class="h-64 rounded-2xl overflow-hidden border border-white/10"></div>
            <p class="text-[11px] text-slate-400">
                Radius toleransi gerai: <b>{{ $task->outlet?->radius_meters }} meter</b>.
            </p>
        </div>

        @if(auth()->user()->canManage() && !$task->isFinal())
            <div class="glass p-5 space-y-3">
                <h3 class="text-sm font-extrabold text-white">Batalkan Penugasan</h3>
                <form method="POST" action="{{ route('hrd.tasks.cancel', $task) }}" class="space-y-3"
                      data-confirm="Batalkan tugas {{ $task->task_number }}?">
                    @csrf
                    <input type="text" name="reason" placeholder="Alasan pembatalan (min. 5 karakter)..." required class="input text-xs">
                    <button type="submit" class="btn btn-danger btn-sm btn-block">Batalkan Tugas Ini</button>
                </form>
            </div>
        @endif
    </div>
</div>

@push('scripts')
<script>
window.hsmReady(() => {
    const el = document.getElementById('task-detail-map');
    if (!el || !window.hsmCreateMap) return;
    const oLat = {{ $task->outlet->latitude ?? -0.502106 }};
    const oLng = {{ $task->outlet->longitude ?? 117.153709 }};
    const oRad = {{ $task->outlet->radius_meters ?? 50 }};
    const map = window.hsmCreateMap(el, oLat, oLng, 16);
    L.circle([oLat, oLng], { radius: oRad, color: '#ff6500', fillColor: '#ff6500', fillOpacity: 0.18 }).addTo(map);
    L.marker([oLat, oLng], { icon: window.hsmPinIcon('#ff6500') }).addTo(map).bindPopup('Titik Gerai: {{ $task->outlet->name }}');

    @if($task->start_latitude && $task->start_longitude)
        L.marker([{{ $task->start_latitude }}, {{ $task->start_longitude }}], { icon: window.hsmPinIcon('#00d2ff') })
            .addTo(map).bindPopup('Lokasi Mulai ({{ $task->start_distance_meters }} m)');
    @endif
    @if($task->end_latitude && $task->end_longitude)
        L.marker([{{ $task->end_latitude }}, {{ $task->end_longitude }}], { icon: window.hsmPinIcon('#10b981') })
            .addTo(map).bindPopup('Lokasi Selesai ({{ $task->end_distance_meters }} m)');
    @endif
});
</script>
@endpush
@endsection
