@extends('layouts.app', ['title' => 'Eksekusi Tugas', 'subtitle' => $task->task_number.' • '.$task->outlet->name])

@section('content')
<div class="max-w-xl mx-auto space-y-5"
     x-data="taskRunner({
         outletLat: {{ $task->outlet->latitude ?? -0.502106 }},
         outletLng: {{ $task->outlet->longitude ?? 117.153709 }},
         outletRadius: {{ $task->outlet->radius_meters ?? 50 }},
         startedAtMs: {{ $task->started_at ? $task->started_at->getTimestamp() * 1000 : 'null' }},
         startUrl: '{{ route('pwa.tasks.start', $task) }}',
         completeUrl: '{{ route('pwa.tasks.complete', $task) }}',
         csrf: '{{ csrf_token() }}'
     })">

    <!-- 3-Stage Stepper Header -->
    <div class="glass p-5 space-y-4">
        <div class="grid grid-cols-3 gap-2 text-center text-[11px] font-bold border-b border-white/10 pb-3">
            <div class="{{ $task->status === 'pending' ? 'text-orange-400' : 'text-emerald-400' }}">
                1. Belum Diproses
            </div>
            <div class="{{ $task->status === 'in_progress' ? 'text-orange-400' : ($task->status === 'completed' ? 'text-emerald-400' : 'text-slate-500') }}">
                2. Sedang Diproses
            </div>
            <div class="{{ $task->status === 'completed' ? 'text-emerald-400' : 'text-slate-500' }}">
                3. Selesai Diproses
            </div>
        </div>

        <div>
            <div class="flex items-center gap-2">
                <span class="badge {{ $task->task_type === 'pasti_harian' ? 'badge-info' : 'badge-orange' }}">{{ $task->typeLabel() }}</span>
                <span class="text-xs font-mono text-slate-400">Jadwal: {{ $task->startTimeShort() }} &bull; SLA {{ $task->estimated_duration_minutes }} menit</span>
            </div>
            <h2 class="text-xl font-extrabold text-white mt-2">{{ $task->title }}</h2>
            <p class="text-xs text-slate-300 mt-1 leading-relaxed">{{ $task->description ?: 'Lakukan pengerjaan sesuai SOP gerai dan catat hasilnya.' }}</p>
        </div>

        <div class="border-t border-white/10 pt-3.5 text-xs space-y-1">
            <div class="font-bold text-white flex items-center gap-1.5 flex-wrap">
                <span>📍 Lokasi: {{ $task->outlet->name }} ({{ $task->outlet->code }})</span>
                @if($task->outlet->is_main_office)
                    <span class="badge badge-orange text-[9px]">Kantor Utama</span>
                @elseif($task->outlet->isCorporate())
                    <span class="badge text-[9px]" style="background: rgba(168, 85, 247, 0.2); color: #c084fc;">Corporate</span>
                @else
                    <span class="badge badge-info text-[9px]">Gerai</span>
                @endif
            </div>
            <div class="text-slate-400">{{ $task->outlet->address }}</div>
            <div class="flex items-center justify-between pt-1">
                <span>Radius Toleransi: <b class="text-white">{{ $task->outlet->radius_meters }} m</b></span>
                <span x-show="distance !== null" class="font-bold"
                      :class="distance <= outletRadius ? 'text-emerald-400' : 'text-amber-400'"
                      x-text="`Jarak Anda: ${distance} m (±${Math.round(accuracy || 10)}m)`"></span>
            </div>
        </div>
    </div>

    @if(!$attended && $task->status === 'pending')
        <div class="glass p-5 border-amber-500/45 bg-amber-500/10 space-y-3 text-xs">
            <div class="font-extrabold text-amber-300 text-sm">Presensi Masuk Diperlukan!</div>
            <p class="text-slate-200">Anda wajib melakukan presensi hadir di Kantor Utama HSM terlebih dahulu sebelum dapat memulai pengerjaan tugas.</p>
            <a href="{{ route('pwa.attendance') }}" class="btn btn-primary btn-block">Buka Halaman Presensi GPS</a>
        </div>
    @endif

    @if(!$task->isFinal())
        <!-- Live Map & Simulation Helper -->
        <div class="glass p-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-xs font-bold text-white">Peta Geofencing Lokasi Tugas (Samarinda)</span>
                <div class="flex gap-1.5">
                    <button type="button" @click="simulateAtOutlet(true)" class="btn btn-ghost btn-sm text-[11px]" title="Simulasi berada di titik gerai Samarinda">
                        Simulasi di Gerai
                    </button>
                    <button type="button" @click="locate()" class="btn btn-ghost btn-sm text-[11px]">
                        GPS Perangkat
                    </button>
                </div>
            </div>
            <div id="pwa-task-map" class="h-60 rounded-2xl overflow-hidden border border-white/10"></div>
        </div>
    @endif

    <!-- Action Box by Status -->
    @if($task->status === 'pending')
        <div class="glass p-5 space-y-3">
            <button type="button"
                    @click="startTask()"
                    :disabled="busy || !{{ $attended ? 'true' : 'false' }}"
                    class="btn btn-primary btn-lg btn-block">
                <span x-text="busy ? 'Mengirim Koordinat GPS...' : 'Mulai Kerjakan Tugas Sekarang'">Mulai Kerjakan Tugas Sekarang</span>
            </button>
            <p class="text-[11px] text-center text-slate-400">
                Sistem akan mengunci waktu mulai dan merekam koordinat GPS Anda saat ini.
            </p>
        </div>
    @elseif($task->status === 'in_progress')
        <div class="glass p-5 space-y-4 border-orange-500/40">
            <div class="flex items-center justify-between border-b border-white/10 pb-3.5">
                <div>
                    <div class="text-[11px] font-bold uppercase text-orange-300">Durasi Berjalan</div>
                    <div class="text-2xl font-mono font-extrabold text-white" x-text="elapsedText">00:00</div>
                </div>
                <div class="text-right text-xs">
                    <div class="text-slate-300">Mulai: <b>{{ $task->started_at?->format('H:i:s') }}</b></div>
                    <div class="text-orange-300">Target SLA: {{ $task->estimated_duration_minutes }} menit</div>
                </div>
            </div>

            <div>
                <label class="label">Catatan Hasil Pengerjaan (Wajib, min. 10 karakter)</label>
                <textarea x-model="notes" rows="3" placeholder="Tuliskan hasil pengecekan/pengerjaan dengan jelas..." class="input"></textarea>
            </div>

            <!-- Bukti Pengerjaan (Kamera Langsung & Kompresi Otomatis) -->
            <div class="space-y-3 border-t border-white/10 pt-4">
                <div class="flex items-center justify-between">
                    <label class="label mb-0 text-white font-bold flex items-center gap-1.5">
                        <span class="text-orange-400">📷</span> Foto Bukti Pengerjaan
                    </label>
                    <span class="text-[11px] text-slate-400">Opsional • Auto-kompresi 1280px</span>
                </div>

                <!-- Hidden file inputs -->
                <input type="file" x-ref="cameraInput" accept="image/*" capture="environment" @change="processProof($event)" class="hidden">
                <input type="file" x-ref="galleryInput" accept="image/*" @change="processProof($event)" class="hidden">

                <!-- Loading Indikator saat proses kompresi foto berlangsung -->
                <div x-show="compressingPhoto" class="p-4 rounded-2xl bg-orange-500/10 border border-orange-500/30 flex items-center justify-center gap-3 text-xs text-orange-200">
                    <svg class="w-5 h-5 animate-spin text-orange-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                        <path d="M3 3v5h5"/>
                        <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/>
                        <path d="M16 16h5v5"/>
                    </svg>
                    <span class="font-bold">Mengompresi foto otomatis (1280px • JPEG 70%)...</span>
                </div>

                <!-- Pilihan Tombol Jika BELUM Ada Foto -->
                <div x-show="!proofPreview && !compressingPhoto" class="space-y-2">
                    <button type="button" @click="$refs.cameraInput.click()"
                            class="btn btn-primary btn-block py-3.5 px-4 rounded-2xl shadow-lg flex items-center justify-center gap-2.5 text-sm font-extrabold transition active:scale-[0.98]">
                        <svg class="w-5 h-5 flex-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        <span>Ambil Foto Kamera Langsung</span>
                    </button>

                    <div class="flex items-center gap-2">
                        <div class="h-px bg-white/10 flex-1"></div>
                        <span class="text-[10px] uppercase font-bold text-slate-500">atau</span>
                        <div class="h-px bg-white/10 flex-1"></div>
                    </div>

                    <button type="button" @click="$refs.galleryInput.click()"
                            class="btn btn-ghost btn-block py-2.5 px-3 rounded-xl border border-white/10 text-xs text-slate-300 hover:text-white flex items-center justify-center gap-2">
                        <svg class="w-4 h-4 flex-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                        </svg>
                        <span>Pilih Foto dari Galeri / File</span>
                    </button>
                </div>

                <!-- Tampilan Preview Foto yang Berhasil Diambil & Dikompresi -->
                <div x-show="proofPreview && !compressingPhoto" class="space-y-2 p-3 rounded-2xl bg-white/5 border border-white/10" style="display:none;">
                    <div class="relative rounded-xl overflow-hidden border border-white/15 bg-black/40">
                        <img :src="proofPreview" alt="Preview Bukti" class="w-full max-h-72 object-contain mx-auto">
                        
                        <!-- Badge status kompresi -->
                        <div class="absolute bottom-2 left-2 right-2 flex flex-wrap items-center justify-between gap-1 p-2 rounded-lg bg-black/80 backdrop-blur-xs text-[11px] text-white">
                            <span class="font-bold flex items-center gap-1 text-emerald-400">
                                ✓ Foto Siap Diunggah
                            </span>
                            <span class="font-mono text-slate-300" x-text="proofInfo ? `${proofInfo.comp} (${proofInfo.saved > 0 ? 'hemat ' + proofInfo.saved + '%' : 'optimal'})` : ''"></span>
                        </div>
                    </div>

                    <!-- Tombol Aksi Ulangi atau Hapus Foto -->
                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <button type="button" @click="$refs.cameraInput.click()" class="btn btn-ghost btn-sm text-xs flex items-center justify-center gap-1.5 border border-white/10 hover:border-orange-400">
                            <svg class="w-3.5 h-3.5 text-orange-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>Ulangi Foto</span>
                        </button>
                        <button type="button" @click="removeProof()" class="btn btn-danger btn-sm text-xs flex items-center justify-center gap-1.5">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            <span>Hapus Foto</span>
                        </button>
                    </div>
                </div>
            </div>

            <button type="button" @click="completeTask()" :disabled="busy" class="btn btn-success btn-lg btn-block">
                <span x-text="busy ? 'Menyimpan & Menghitung KPI...' : 'Tandai Selesai Diproses'">Tandai Selesai Diproses</span>
            </button>
        </div>
    @elseif($task->status === 'completed')
        <div class="glass p-5 space-y-3 border-emerald-500/40">
            <div class="flex items-center justify-between">
                <span class="badge badge-success">Tugas Selesai Diproses</span>
                <span class="text-xs font-mono text-emerald-300">Durasi Aktual: {{ $task->actual_duration_minutes }} menit</span>
            </div>
            <div class="text-xs text-slate-300 space-y-1">
                <div>Waktu Mulai: <b>{{ $task->started_at?->format('d/m/Y H:i:s') }}</b> ({{ $task->start_distance_meters }}m dari gerai)</div>
                <div>Waktu Selesai: <b>{{ $task->completed_at?->format('d/m/Y H:i:s') }}</b> ({{ $task->end_distance_meters }}m dari gerai)</div>
                <div>Validasi Lokasi: <b class="{{ $task->is_location_valid ? 'text-emerald-400' : 'text-red-400' }}">{{ $task->is_location_valid ? 'Valid di Dalam Radius' : 'Di Luar Radius' }}</b></div>
            </div>
            @if($task->notes)
                <div class="border-t border-white/10 pt-3 text-xs text-slate-200">
                    <b>Catatan:</b> {{ $task->notes }}
                </div>
            @endif
            @if($task->proofUrl())
                <div class="space-y-1.5 pt-2">
                    <div class="text-[11px] font-bold text-slate-300 flex items-center justify-between">
                        <span>Foto Bukti:</span>
                        <a href="{{ $task->proofUrl() }}" target="_blank" rel="noopener noreferrer" class="text-orange-400 text-[11px] underline">
                            Buka Ukuran Penuh ↗
                        </a>
                    </div>
                    <a href="{{ $task->proofUrl() }}" target="_blank" rel="noopener noreferrer" class="block">
                        <img src="{{ $task->proofUrl() }}" alt="Bukti {{ $task->task_number }}" class="rounded-2xl max-h-64 border border-white/10 shadow object-contain bg-slate-900/40">
                    </a>
                </div>
            @endif
        </div>
    @endif
</div>

@push('scripts')
<script>
function taskRunner(cfg) {
    return {
        lat: null,
        lng: null,
        accuracy: null,
        distance: null,
        outletRadius: cfg.outletRadius,
        notes: '',
        proofFile: null,
        proofPreview: null,
        proofInfo: null,
        compressingPhoto: false,
        busy: false,
        elapsedText: '00:00',
        map: null,
        meMarker: null,

        init() {
            const mapEl = document.getElementById('pwa-task-map');
            if (mapEl && window.hsmCreateMap) {
                this.map = window.hsmCreateMap(mapEl, cfg.outletLat, cfg.outletLng, 16);
                L.circle([cfg.outletLat, cfg.outletLng], { radius: cfg.outletRadius, color: '#ff6500', fillColor: '#ff6500', fillOpacity: 0.18 }).addTo(this.map);
                L.marker([cfg.outletLat, cfg.outletLng], { icon: window.hsmPinIcon('#ff6500') }).addTo(this.map).bindPopup('Titik Gerai Samarinda');
            }
            // Default ke titik gerai di Kota Samarinda
            this.simulateAtOutlet(false);
            if (cfg.startedAtMs) {
                this.tick();
                setInterval(() => this.tick(), 1000);
            }
        },
        tick() {
            const sec = Math.max(0, Math.floor((Date.now() - cfg.startedAtMs) / 1000));
            const m = String(Math.floor(sec / 60)).padStart(2, '0');
            const s = String(sec % 60).padStart(2, '0');
            this.elapsedText = `${m}:${s}`;
        },
        setCoords(lat, lng, acc = 10) {
            this.lat = lat;
            this.lng = lng;
            this.accuracy = acc;
            this.distance = window.hsmDistanceMeters(lat, lng, cfg.outletLat, cfg.outletLng);
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
                this.simulateAtOutlet(true);
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.setCoords(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy || 12);
                    window.HsmSwal?.toast('Koordinat GPS perangkat diperbarui', 'success');
                },
                () => this.simulateAtOutlet(true),
                { enableHighAccuracy: true, timeout: 6000 }
            );
        },
        simulateAtOutlet(showToast = false) {
            this.setCoords(cfg.outletLat + 0.00008, cfg.outletLng + 0.00008, 8);
            if (showToast) {
                window.HsmSwal?.toast('Koordinat disimulasikan di dekat gerai Samarinda (±12m)', 'info');
            }
        },
        async processProof(e) {
            const f = e.target.files?.[0];
            if (!f) return;
            this.compressingPhoto = true;
            try {
                const res = await window.hsmCompressImage(f, 1280, 0.70);
                this.proofFile = res.file;
                this.proofPreview = res.previewUrl;
                this.proofInfo = {
                    orig: window.hsmFormatBytes(res.originalSize),
                    comp: window.hsmFormatBytes(res.compressedSize),
                    saved: res.savedPercent || 0,
                    dim: res.width ? `${res.width}x${res.height}` : ''
                };
            } catch (err) {
                console.error('Kompresi foto gagal:', err);
                this.proofFile = f;
                this.proofPreview = URL.createObjectURL(f);
                this.proofInfo = {
                    orig: window.hsmFormatBytes(f.size),
                    comp: window.hsmFormatBytes(f.size),
                    saved: 0,
                    dim: ''
                };
            } finally {
                this.compressingPhoto = false;
                e.target.value = '';
            }
        },
        removeProof() {
            if (this.proofPreview && this.proofPreview.startsWith('blob:')) {
                URL.revokeObjectURL(this.proofPreview);
            }
            this.proofFile = null;
            this.proofPreview = null;
            this.proofInfo = null;
            if (this.$refs.cameraInput) this.$refs.cameraInput.value = '';
            if (this.$refs.galleryInput) this.$refs.galleryInput.value = '';
        },
        async startTask() {
            if (this.lat === null) this.simulateAtOutlet();
            this.busy = true;
            try {
                const res = await fetch(cfg.startUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: JSON.stringify({ latitude: this.lat, longitude: this.lng, accuracy: this.accuracy })
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    await window.HsmSwal.popup({ icon: 'error', title: 'Gagal Memulai Tugas', text: data.message || 'Terjadi kesalahan.' });
                    return;
                }
                await window.HsmSwal.popup({ icon: data.in_radius ? 'success' : 'warning', title: 'Status Diperbarui', text: data.message });
                window.location.reload();
            } finally {
                this.busy = false;
            }
        },
        async completeTask() {
            if ((this.notes || '').trim().length < 10) {
                window.HsmSwal.popup({ icon: 'warning', title: 'Catatan Kurang Lengkap', text: 'Wajib mengisi catatan pengerjaan minimal 10 karakter.' });
                return;
            }
            if (this.lat === null) this.simulateAtOutlet();
            this.busy = true;
            try {
                const fd = new FormData();
                fd.append('latitude', this.lat);
                fd.append('longitude', this.lng);
                fd.append('accuracy', this.accuracy ?? 10);
                fd.append('notes', this.notes);
                if (this.proofFile) fd.append('proof', this.proofFile);

                const res = await fetch(cfg.completeUrl, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': cfg.csrf },
                    body: fd
                });
                const data = await res.json();
                if (!res.ok || !data.ok) {
                    await window.HsmSwal.popup({ icon: 'error', title: 'Gagal Menyelesaikan', text: data.message || 'Periksa kembali input Anda.' });
                    return;
                }
                await window.HsmSwal.popup({ icon: 'success', title: 'Tugas Selesai!', text: data.message });
                window.location.href = data.redirect || '{{ route('pwa.tasks.index') }}';
            } finally {
                this.busy = false;
            }
        }
    };
}
</script>
@endpush
@endsection
