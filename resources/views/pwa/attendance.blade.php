@extends('layouts.app', ['title' => 'Data & Riwayat Presensi HSM', 'subtitle' => 'Sinkronisasi jam masuk, jam pulang, radius kantor, dan selfie dari tikismd.id'])

@section('content')
<div class="max-w-xl mx-auto space-y-4"
     x-data="{
         preview: null,
         openPhoto(url, label, meta) {
             this.preview = { url, label, meta };
         }
     }"
     @keydown.escape.window="preview = null">

    <!-- Today's Synced Attendance Card -->
    <div class="glass p-5 space-y-4 rise">
        <div class="flex items-start justify-between gap-3 flex-wrap">
            <div>
                <span class="badge badge-info">Terintegrasi Portal Absensi HSM</span>
                <h2 class="text-lg font-extrabold text-white mt-1.5">Status Kehadiran Hari Ini</h2>
                <p class="text-xs text-slate-400">{{ now()->translatedFormat('l, d F Y') }}</p>
            </div>
            @if($today)
                <div class="flex flex-col items-end gap-1">
                    <span class="badge {{ $today->statusBadge() }}">{{ $today->statusLabel() }}</span>
                    <span class="badge {{ $today->validationBadge() }}">{{ $today->validationLabel() }}</span>
                </div>
            @elseif($todayStatus && $todayStatus->isNonWorkingLeave())
                <span class="badge {{ $todayStatus->statusBadge() }}">{{ $todayStatus->statusLabel() }}</span>
            @else
                <span class="badge badge-warning">Belum Tercatat</span>
            @endif
        </div>

        @if($today)
            @php
                $inUrl = $today->checkInPhotoUrl();
                $outUrl = $today->checkOutPhotoUrl();
            @endphp

            <!-- Check-in & Check-out Time Grid -->
            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200">
                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                    <div class="text-[11px] font-bold uppercase text-slate-500">Jam Masuk</div>
                    <div class="text-lg font-mono font-extrabold text-slate-900 mt-0.5">{{ $today->check_in_at->format('H:i:s') }}</div>
                    <div class="text-[11px] {{ $today->late_minutes > 0 ? 'text-amber-700 font-semibold' : 'text-emerald-700 font-semibold' }}">
                        WITA &bull; {{ $today->late_minutes > 0 ? 'Terlambat '.$today->late_minutes.' mnt' : 'Tepat waktu' }}
                    </div>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 border border-slate-200">
                    <div class="text-[11px] font-bold uppercase text-slate-500">Jam Pulang</div>
                    <div class="text-lg font-mono font-extrabold text-slate-900 mt-0.5">
                        {{ $today->check_out_at ? $today->check_out_at->format('H:i:s') : '--:--' }}
                    </div>
                    <div class="text-[11px] text-slate-600">
                        {{ $today->check_out_at ? 'WITA • Durasi '.($today->workDurationFormatted() ?? '-') : 'Masih bertugas aktif' }}
                    </div>
                </div>
            </div>

            <!-- Office Location & Geofence Radius -->
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-1.5 text-xs">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <span class="font-extrabold text-slate-900">
                        Lokasi Kantor: {{ $today->officeDisplayName() }}
                        @if($today->shift_name)
                            ({{ $today->shift_name }})
                        @endif
                    </span>
                    <span class="font-bold {{ $today->isWithinCheckInRadius() ? 'text-emerald-700' : 'text-red-700' }}">
                        {{ $today->isWithinCheckInRadius() ? 'Dalam Radius Kantor' : 'Di Luar Radius Kantor' }}
                    </span>
                </div>
                @if($today->location_address)
                    <div class="text-slate-600">{{ $today->location_address }}</div>
                @endif
                <div class="flex flex-wrap items-center justify-between gap-2 pt-1 text-slate-700">
                    <span>Jarak Masuk: <b>{{ $today->formattedDistance($today->check_in_distance_meters !== null ? (float) $today->check_in_distance_meters : null) }}</b> dari batas radius <b>{{ $today->officeRadiusMeters() }} m</b></span>
                    @if($today->check_out_distance_meters !== null)
                        <span>Jarak Pulang: <b>{{ $today->formattedDistance((float) $today->check_out_distance_meters) }}</b></span>
                    @endif
                </div>
                @if($today->notes)
                    <div class="text-amber-800 font-medium pt-1 border-t border-slate-200">Catatan HRD: {{ $today->notes }}</div>
                @endif
            </div>

            <!-- Selfie Photos (Check-In & Check-Out) -->
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-700">
                        <span>Selfie Masuk</span>
                        <span class="font-mono text-slate-500">{{ $today->check_in_at->format('H:i') }}</span>
                    </div>
                    @if($inUrl)
                        <button type="button"
                                @click="openPhoto(@js($inUrl), 'Selfie Presensi Masuk', @js($today->check_in_at->format('d M Y H:i:s').' WITA • '.$today->officeDisplayName()))"
                                class="block w-full h-36 rounded-lg overflow-hidden border border-slate-200 bg-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <img src="{{ $inUrl }}" alt="Foto selfie presensi masuk" class="w-full h-full object-cover">
                        </button>
                    @else
                        <div class="h-36 rounded-lg border border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-500 text-center px-2">
                            Foto selfie masuk tidak tersedia
                        </div>
                    @endif
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-2.5 space-y-2">
                    <div class="flex items-center justify-between text-[11px] font-bold text-slate-700">
                        <span>Selfie Pulang</span>
                        <span class="font-mono text-slate-500">{{ $today->check_out_at ? $today->check_out_at->format('H:i') : '-' }}</span>
                    </div>
                    @if($outUrl)
                        <button type="button"
                                @click="openPhoto(@js($outUrl), 'Selfie Presensi Pulang', @js(($today->check_out_at?->format('d M Y H:i:s') ?? '').' WITA • '.$today->officeDisplayName()))"
                                class="block w-full h-36 rounded-lg overflow-hidden border border-slate-200 bg-slate-900 focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <img src="{{ $outUrl }}" alt="Foto selfie presensi pulang" class="w-full h-full object-cover">
                        </button>
                    @else
                        <div class="h-36 rounded-lg border border-dashed border-slate-300 flex items-center justify-center text-xs text-slate-500 text-center px-2">
                            {{ $today->check_out_at ? 'Foto pulang tidak tersedia' : 'Belum presensi pulang' }}
                        </div>
                    @endif
                </div>
            </div>

            @if($today->check_in_latitude && $today->check_in_longitude)
                <div id="pwa-hsm-attendance-map" class="h-48 rounded-2xl overflow-hidden border border-slate-200"></div>
            @endif

        @elseif($todayStatus && $todayStatus->isNonWorkingLeave())
            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1">
                <div class="font-extrabold text-slate-900 text-sm">Status Kehadiran: {{ $todayStatus->statusLabel() }}</div>
                <p class="text-slate-600">{{ $todayStatus->notes ?: 'Data izin/cuti Anda hari ini telah tercatat pada Portal Absensi HSM.' }}</p>
            </div>
        @else
            <div class="p-4 rounded-xl bg-amber-50 border border-amber-200 text-xs space-y-3">
                <div class="font-extrabold text-amber-900 text-sm">Belum Ada Data Presensi Masuk Hari Ini</div>
                <p class="text-amber-900/90 leading-relaxed">
                    Pencatatan presensi masuk, presensi pulang, pengecekan radius kantor, dan foto selfie wajah sepenuhnya dilakukan melalui <b>Portal Absensi HSM ({{ parse_url($portalUrl, PHP_URL_HOST) ?: 'tikismd.id' }})</b>. Setelah Anda absen di sana, data otomatis tampil di halaman ini.
                </p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ $portalUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary min-h-11 flex-1">
                        <x-icon name="external-link" class="w-4 h-4" /> Buka Portal Absensi HSM
                    </a>
                    <a href="{{ route('pwa.attendance') }}" class="btn btn-ghost min-h-11">
                        <x-icon name="repeat" class="w-4 h-4" /> Muat Ulang
                    </a>
                </div>
            </div>
        @endif
    </div>

    <!-- Recent Attendance History from tikismd.id -->
    <div class="space-y-3 rise rise-2">
        <div class="px-1 flex items-center justify-between">
            <div>
                <div class="orn-line mb-1"></div>
                <h3 class="text-base font-extrabold text-white">Riwayat Presensi Terakhir</h3>
            </div>
            <span class="text-[11px] text-slate-500 font-medium">{{ $history->count() }} catatan</span>
        </div>

        <div class="space-y-2.5">
            @forelse($history as $h)
                @php
                    $hInUrl = $h->checkInPhotoUrl();
                    $hOutUrl = $h->checkOutPhotoUrl();
                @endphp
                <div class="glass p-4 space-y-2.5 text-xs">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <div class="font-extrabold text-white text-sm">{{ $h->date->translatedFormat('l, d M Y') }}</div>
                            <div class="text-[11px] text-slate-500 font-medium">
                                {{ $h->officeDisplayName() }}
                                @if($h->hasCheckedIn())
                                    &bull; {{ $h->radiusSummary() }}
                                @endif
                            </div>
                        </div>
                        <span class="badge {{ $h->statusBadge() }} flex-none">{{ $h->statusLabel() }}</span>
                    </div>

                    @if($h->hasCheckedIn())
                        <div class="flex items-center justify-between gap-3 pt-2 border-t border-slate-200/80">
                            <div class="space-y-0.5 text-slate-700">
                                <div>Masuk: <b class="font-mono text-slate-900">{{ $h->check_in_at->format('H:i:s') }} WITA</b></div>
                                <div>Pulang: <b class="font-mono text-slate-900">{{ $h->check_out_at ? $h->check_out_at->format('H:i:s').' WITA' : 'Belum pulang' }}</b></div>
                            </div>
                            <div class="flex items-center gap-1.5 flex-none">
                                @if($hInUrl)
                                    <button type="button"
                                            @click="openPhoto(@js($hInUrl), 'Selfie Masuk ' + @js($h->date->format('d/m/Y')), @js($h->check_in_at->format('H:i:s').' WITA • '.$h->officeDisplayName()))"
                                            class="w-11 h-11 rounded-xl overflow-hidden border border-emerald-300 bg-slate-100 relative"
                                            aria-label="Lihat foto selfie masuk {{ $h->date->format('d/m/Y') }}">
                                        <img src="{{ $hInUrl }}" alt="Selfie masuk" loading="lazy" class="w-full h-full object-cover">
                                        <span class="absolute bottom-0 inset-x-0 bg-slate-900/75 text-[8px] font-bold text-white text-center">IN</span>
                                    </button>
                                @endif
                                @if($hOutUrl)
                                    <button type="button"
                                            @click="openPhoto(@js($hOutUrl), 'Selfie Pulang ' + @js($h->date->format('d/m/Y')), @js(($h->check_out_at?->format('H:i:s') ?? '').' WITA • '.$h->officeDisplayName()))"
                                            class="w-11 h-11 rounded-xl overflow-hidden border border-slate-300 bg-slate-100 relative"
                                            aria-label="Lihat foto selfie pulang {{ $h->date->format('d/m/Y') }}">
                                        <img src="{{ $hOutUrl }}" alt="Selfie pulang" loading="lazy" class="w-full h-full object-cover">
                                        <span class="absolute bottom-0 inset-x-0 bg-slate-900/75 text-[8px] font-bold text-white text-center">OUT</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @elseif($h->notes)
                        <div class="text-slate-600 pt-1 border-t border-slate-200/80">{{ $h->notes }}</div>
                    @endif
                </div>
            @empty
                <div class="glass p-6 text-xs text-slate-400 text-center">
                    Belum ada riwayat presensi tercatat di Portal Absensi HSM.
                </div>
            @endforelse
        </div>
    </div>

    <!-- Fullscreen Selfie Lightbox -->
    <div x-show="preview !== null" x-cloak
         class="fixed inset-0 z-[9999] bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4"
         role="dialog" aria-modal="true">
        <div @click.outside="preview = null" class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden">
            <div class="p-4 border-b border-slate-200 flex items-center justify-between gap-2">
                <div class="min-w-0">
                    <div class="text-sm font-extrabold text-slate-900 truncate" x-text="preview?.label"></div>
                    <div class="text-xs text-slate-600 truncate" x-text="preview?.meta"></div>
                </div>
                <button type="button" @click="preview = null" class="btn btn-ghost btn-sm min-h-11 min-w-11 p-2" aria-label="Tutup foto">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>
            <div class="bg-slate-950 flex items-center justify-center max-h-[72dvh]">
                <template x-if="preview">
                    <img :src="preview.url" :alt="preview.label" class="max-h-[72dvh] w-auto object-contain">
                </template>
            </div>
        </div>
    </div>
</div>

@if($today && $today->check_in_latitude && $today->check_in_longitude)
@push('scripts')
<script>
window.hsmReady(() => {
    const el = document.getElementById('pwa-hsm-attendance-map');
    if (!el || !window.hsmCreateMap) return;
    const inLat = {{ (float) $today->check_in_latitude }};
    const inLng = {{ (float) $today->check_in_longitude }};
    const offLat = {{ (float) ($today->office_latitude ?? $today->check_in_latitude) }};
    const offLng = {{ (float) ($today->office_longitude ?? $today->check_in_longitude) }};
    const radius = {{ (int) $today->officeRadiusMeters() }};

    const map = window.hsmCreateMap(el, offLat, offLng, 17);
    L.circle([offLat, offLng], { radius, color: '#ea580c', fillColor: '#ea580c', fillOpacity: 0.14 }).addTo(map);
    L.marker([offLat, offLng], { icon: window.hsmPinIcon('#ea580c') })
        .addTo(map)
        .bindPopup(@js($today->officeDisplayName().' (Radius '.$today->officeRadiusMeters().'m)'));
    L.marker([inLat, inLng], { icon: window.hsmPinIcon('#10b981', 'rgba(16,185,129,0.35)') })
        .addTo(map)
        .bindPopup(@js('Titik Presensi Masuk ('.$today->check_in_at->format('H:i:s').' WITA)'));
});
</script>
@endpush
@endif
@endsection
