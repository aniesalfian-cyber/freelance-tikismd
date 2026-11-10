@extends('layouts.app', ['title' => 'Presensi GPS Kantor Utama', 'subtitle' => 'Validasi kehadiran dengan Geofencing Haversine'])

@section('content')
<div class="max-w-xl mx-auto space-y-5"
     x-data="attendanceApp({
         officeLat: {{ $office?->latitude ?? -0.502106 }},
         officeLng: {{ $office?->longitude ?? 117.153709 }},
         officeRadius: {{ $office?->radius_meters ?? 50 }},
         checkInUrl: '{{ route('pwa.attendance.checkin') }}',
         checkOutUrl: '{{ route('pwa.attendance.checkout') }}',
         csrf: '{{ csrf_token() }}'
     })">

    <!-- Main Office Geofence Card -->
    <div class="glass p-5 space-y-4 rise">
        <div class="flex items-start justify-between gap-3">
            <div>
                <span class="badge badge-orange">Titik Presensi Wajib • Kota Samarinda</span>
                <h2 class="text-lg font-extrabold text-white mt-1">{{ $office?->name ?? 'Kantor Utama HSM Samarinda' }}</h2>
                <p class="text-xs text-slate-400">{{ $office?->address ?? 'Jl. Gajah Mada No. 1, Kota Samarinda, Kalimantan Timur' }}</p>
            </div>
            <div class="text-right text-xs">
                <div class="text-slate-400">Radius Maks</div>
                <div class="text-base font-extrabold text-white">{{ $office?->radius_meters ?? 50 }} m</div>
            </div>
        </div>

        <div id="pwa-attendance-map" class="h-64 rounded-2xl overflow-hidden border border-white/10"></div>

        <!-- Distance Telemetry & Simulation Controls -->
        <div class="p-3.5 rounded-2xl bg-navy-950/75 border border-white/10 flex flex-wrap items-center justify-between gap-2 text-xs">
            <div>
                <div class="text-slate-400">Status Jarak Anda Saat Ini (Kota Samarinda):</div>
                <div class="font-extrabold text-sm mt-0.5"
                     :class="distance !== null && distance <= officeRadius ? 'text-emerald-400' : 'text-amber-400'"
                     x-text="distance !== null ? `${distance} meter dari Kantor Utama (Akurasi ±${Math.round(accuracy||10)}m)` : 'Memindai sinyal GPS...'"></div>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button type="button" @click="simulateInside(true)" class="btn btn-ghost btn-sm text-[11px]" title="Simulasi berada di dalam kantor utama Samarinda">
                    Simulasi Di Kantor
                </button>
                <button type="button" @click="simulateOutside()" class="btn btn-ghost btn-sm text-[11px]" title="Simulasi berada di luar radius (180m)">
                    Simulasi Luar (180m)
                </button>
                <button type="button" @click="locate()" class="btn btn-ghost btn-sm text-[11px]" title="Ambil posisi dari sensor GPS perangkat">
                    GPS Perangkat
                </button>
            </div>
        </div>

        <!-- Check-In / Check-Out Buttons -->
        @if(!$today)
            <button type="button" @click="submit(checkInUrl, 'Presensi Masuk')" :disabled="busy" class="btn btn-primary btn-lg btn-block">
                <x-icon name="pin" class="w-5 h-5" />
                <span x-text="busy ? 'Memvalidasi Geofencing...' : 'Presensi Masuk Sekarang'">Presensi Masuk Sekarang</span>
            </button>
        @elseif(!$today->check_out_at)
            <div class="p-3.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/35 text-xs flex items-center justify-between">
                <span>Sudah Presensi Masuk pukul <b>{{ $today->check_in_at->format('H:i:s') }} WIB</b> ({{ $today->check_in_distance_meters }}m)</span>
                <span class="badge {{ $today->statusBadge() }}">{{ $today->statusLabel() }}</span>
            </div>
            <button type="button" @click="submit(checkOutUrl, 'Presensi Pulang')" :disabled="busy" class="btn btn-primary btn-lg btn-block">
                <span x-text="busy ? 'Memvalidasi Geofencing...' : 'Presensi Pulang Sekarang'">Presensi Pulang Sekarang</span>
            </button>
        @else
            <div class="p-4 rounded-2xl bg-emerald-500/15 border border-emerald-500/40 text-center space-y-1">
                <div class="text-sm font-extrabold text-emerald-300">Presensi Hari Ini Selesai Lengkap</div>
                <div class="text-xs text-slate-300">
                    Masuk: <b>{{ $today->check_in_at->format('H:i') }}</b> &bull; Pulang: <b>{{ $today->check_out_at->format('H:i') }}</b>
                </div>
            </div>
        @endif
    </div>

    <!-- Attendance History -->
    <div class="glass p-5 space-y-3 rise rise-2">
        <div class="orn-line mb-1"></div>
        <h3 class="text-base font-extrabold text-white">Riwayat Presensi Terakhir</h3>
        <div class="space-y-2">
            @forelse($history as $h)
                <div class="p-3 rounded-xl bg-white/[0.03] border border-white/10 flex items-center justify-between text-xs">
                    <div>
                        <div class="font-bold text-white">{{ $h->date->translatedFormat('l, d M Y') }}</div>
                        <div class="text-slate-400">Masuk {{ $h->check_in_at->format('H:i') }} &bull; Pulang {{ $h->check_out_at?->format('H:i') ?? '-' }} ({{ $h->check_in_distance_meters }}m)</div>
                    </div>
                    <span class="badge {{ $h->statusBadge() }}">{{ $h->statusLabel() }}</span>
                </div>
            @empty
                <p class="text-xs text-slate-400 text-center py-4">Belum ada riwayat presensi.</p>
            @endforelse
        </div>
    </div>
</div>

@push('scripts')
<script>
function attendanceApp(cfg) {
    return {
        lat: null,
        lng: null,
        accuracy: null,
        distance: null,
        officeRadius: cfg.officeRadius,
        checkInUrl: cfg.checkInUrl,
        checkOutUrl: cfg.checkOutUrl,
        busy: false,
        map: null,
        meMarker: null,

        init() {
            const el = document.getElementById('pwa-attendance-map');
            if (el && window.hsmCreateMap) {
                this.map = window.hsmCreateMap(el, cfg.officeLat, cfg.officeLng, 17);
                L.circle([cfg.officeLat, cfg.officeLng], { radius: cfg.officeRadius, color: '#ff6500', fillColor: '#ff6500', fillOpacity: 0.18 }).addTo(this.map);
                L.marker([cfg.officeLat, cfg.officeLng], { icon: window.hsmPinIcon('#ff6500') })
                    .addTo(this.map)
                    .bindPopup('<b>Kantor Utama HSM</b><br>Kota Samarinda');
            }
            // Default ke koordinat Kantor Utama di Kota Samarinda agar peta langsung tampil akurat
            this.simulateInside(false);
        },
        setCoords(lat, lng, acc = 10) {
            this.lat = lat;
            this.lng = lng;
            this.accuracy = acc;
            this.distance = window.hsmDistanceMeters(lat, lng, cfg.officeLat, cfg.officeLng);
            if (this.map) {
                if (!this.meMarker) {
                    this.meMarker = L.marker([lat, lng], { icon: window.hsmPinIcon('#00d2ff', 'rgba(0,210,255,0.35)') }).addTo(this.map).bindPopup('Posisi Anda');
                } else {
                    this.meMarker.setLatLng([lat, lng]);
                }
                this.map.panTo([lat, lng]);
            }
        },
        locate() {
            if (!navigator.geolocation) {
                this.simulateInside(true);
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.setCoords(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy || 10);
                    window.HsmSwal?.toast('Koordinat GPS perangkat berhasil diperbarui', 'success');
                },
                () => this.simulateInside(true),
                { enableHighAccuracy: true, timeout: 6000 }
            );
        },
        simulateInside(showToast = false) {
            this.setCoords(cfg.officeLat + 0.00010, cfg.officeLng + 0.00010, 8);
            if (showToast) window.HsmSwal?.toast('Koordinat disetel di Kantor Utama Samarinda (±15m)', 'info');
        },
        simulateOutside() {
            this.setCoords(cfg.officeLat + 0.00160, cfg.officeLng + 0.00020, 10);
            window.HsmSwal?.toast('Koordinat disimulasikan 180m dari Kantor Utama Samarinda', 'warning');
        },
        async submit(url, title) {
            if (this.lat === null) this.simulateInside();
            this.busy = true;
            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: JSON.stringify({ latitude: this.lat, longitude: this.lng, accuracy: this.accuracy })
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    await window.HsmSwal.popup({ icon: 'error', title: title + ' Ditolak', text: data.message || 'Gagal memvalidasi lokasi.' });
                    return;
                }
                await window.HsmSwal.popup({ icon: 'success', title: title + ' Berhasil', text: data.message });
                window.location.reload();
            } finally {
                this.busy = false;
            }
        }
    };
}
</script>
@endpush
@endsection
