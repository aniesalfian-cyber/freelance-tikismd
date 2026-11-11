@extends('layouts.app', ['title' => 'Delivery & Pick-Up PT. HSM', 'dashboard_name' => 'Dashboard Operasional HRD & Supervisor', 'subtitle' => 'Monitoring real-time posisi GPS kurir, kehadiran, distribusi tugas, dan status gerai hari ini'])

@section('content')
<!-- Top Operational KPI Cards (Compact) -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 rise">
    <div class="glass glass-hover p-3.5">
        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Kehadiran Hari Ini</div>
        <div class="mt-1 flex items-baseline gap-1.5">
            <span class="text-2xl font-extrabold text-emerald-400">{{ $today['present'] }}</span>
            <span class="text-xs text-slate-400">/ {{ $today['freelancers_total'] }}</span>
        </div>
        <div class="text-[11px] text-amber-400">{{ $today['late_attendance'] }} hadir terlambat</div>
    </div>

    <div class="glass glass-hover p-3.5">
        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Sedang Diproses</div>
        <div class="mt-1 text-2xl font-extrabold text-orange-400">{{ $today['task_counts']['in_progress'] }}</div>
        <div class="text-[11px] text-slate-400">Dari {{ $today['task_counts']['total'] }} tugas hari ini</div>
    </div>

    <div class="glass glass-hover p-3.5">
        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Selesai Diproses</div>
        <div class="mt-1 text-2xl font-extrabold text-white">{{ $today['task_counts']['completed'] }}</div>
        <div class="text-[11px] text-emerald-400">
            {{ $today['task_counts']['total'] ? round($today['task_counts']['completed'] / $today['task_counts']['total'] * 100) : 0 }}% penyelesaian
        </div>
    </div>

    <div class="glass glass-hover p-3.5">
        <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Melewati Jam Mulai</div>
        <div class="mt-1 text-2xl font-extrabold text-red-400">{{ $today['late_tasks'] }}</div>
        <div class="text-[11px] text-slate-400">{{ $today['task_counts']['pending'] }} belum diproses</div>
    </div>
</div>

<!-- Quick Actions Bar (Compact) -->
<div class="glass p-3 flex flex-wrap items-center justify-between gap-2 rise rise-2">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('hrd.tasks.create') }}" class="btn btn-primary btn-sm">
            <x-icon name="plus" class="w-4 h-4" /> Beri Tugas Baru
        </a>
        <a href="{{ route('hrd.tasks.import.show') }}" class="btn btn-ghost btn-sm">
            <x-icon name="upload" class="w-4 h-4" /> Import Excel
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

<!-- Live Courier Position & Quick Task Assignment Map Section -->
<div x-data="hrdLiveMapController()" x-init="initMap()"
     @keydown.escape.window="if (quickModalOpen) { quickModalOpen = false; } else if (isFullscreen) { toggleFullscreen(); }"
     :class="isFullscreen ? 'fixed inset-0 z-50 bg-slate-100 p-3 sm:p-6 flex flex-col overflow-hidden' : 'rise rise-3'">

    <div class="glass p-4 sm:p-5 flex flex-col gap-3.5" :class="isFullscreen ? 'flex-1 h-full' : ''">
        <!-- Map Header & Controls -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <div class="orn-line mb-1"></div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h2 class="text-base sm:text-lg font-extrabold text-slate-900">
                        Peta Sebaran Posisi Karyawan & Gerai Live
                    </h2>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                        <span x-text="workingCouriersCount() + ' Kurir Masuk Kerja'"></span>
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">
                    Klik pada titik lokasi karyawan di peta untuk melihat detail posisi dan memberikan <b>Tugas Dadakan</b> secara langsung.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Filter Posisi Kurir -->
                <select x-model="filterMode" @change="renderMarkers(false)" class="input py-1.5 px-3 text-xs w-auto font-semibold">
                    <option value="all">Semua Kurir & Gerai</option>
                    <option value="working">Hanya Kurir Masuk Kerja</option>
                    <option value="R4">Divisi R4 (Mobil Box)</option>
                    <option value="R2">Divisi R2 (Sepeda Motor)</option>
                </select>

                <button type="button" @click="refreshPositions()" class="btn btn-ghost btn-sm" :disabled="loading">
                    <x-icon name="repeat" class="w-3.5 h-3.5" />
                    <span x-text="loading ? 'Memuat...' : 'Refresh (' + lastUpdated + ')'"></span>
                </button>

                <button type="button" @click="toggleFullscreen()" class="btn btn-primary btn-sm">
                    <template x-if="!isFullscreen">
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon name="maximize" class="w-3.5 h-3.5" /> Layar Penuh (Fullscreen)
                        </span>
                    </template>
                    <template x-if="isFullscreen">
                        <span class="inline-flex items-center gap-1.5">
                            <x-icon name="minimize" class="w-3.5 h-3.5" /> Keluar Fullscreen
                        </span>
                    </template>
                </button>
            </div>
        </div>

        <!-- Legend Bar -->
        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] text-slate-600 bg-slate-50 px-3.5 py-2 rounded-xl border border-slate-200">
            <div class="flex flex-wrap items-center gap-3">
                <span class="font-bold text-slate-800">Legenda Peta:</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-emerald-500 border border-white shadow-xs"></span> Kurir R2 Masuk Kerja</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-600 border border-white shadow-xs"></span> Kurir R4 Masuk Kerja</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-slate-400 border border-white shadow-xs"></span> Belum Presensi / Pulang</span>
                <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span> Titik Gerai HSM</span>
            </div>
            <div class="text-slate-500 font-medium">
                Auto-update GPS setiap 15 detik
            </div>
        </div>

        <!-- Map + Interactive Courier Roster Layout -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-3" :class="isFullscreen ? 'flex-1 min-h-0' : ''">
            <!-- Map Canvas -->
            <div class="lg:col-span-3 relative rounded-2xl overflow-hidden border border-slate-300 shadow-inner"
                 :class="isFullscreen ? 'h-full min-h-[420px]' : 'h-[330px]'">
                <div id="hrd-live-map" class="w-full h-full"></div>
            </div>

            <!-- Courier Live Roster Sidebar (Click to focus & assign task) -->
            <div class="glass p-3 flex flex-col justify-between border border-slate-200 overflow-hidden"
                 :class="isFullscreen ? 'h-full' : 'max-h-[330px]'">
                <div class="pb-2 border-b border-slate-200 flex items-center justify-between">
                    <span class="text-xs font-extrabold text-slate-900">Daftar Posisi Kurir (<span x-text="filteredCouriers().length"></span>)</span>
                    <span class="text-[10px] font-bold text-orange-600">Klik untuk Fokus / Tugas</span>
                </div>

                <div class="flex-1 overflow-y-auto divide-y divide-slate-200/70 my-1 pr-1">
                    <template x-for="c in filteredCouriers()" :key="c.id">
                        <div class="py-2.5 px-1.5 hover:bg-orange-50/50 rounded-lg transition flex items-start justify-between gap-2">
                            <button type="button" @click="focusCourier(c)" class="text-left min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full flex-none"
                                          :class="c.is_working ? 'bg-emerald-500' : 'bg-slate-400'"></span>
                                    <span class="text-xs font-extrabold text-slate-900 truncate" x-text="c.name"></span>
                                    <span class="badge badge-muted text-[9px] px-1.5 py-0" x-text="c.vehicle_type"></span>
                                </div>
                                <div class="text-[10px] text-slate-500 truncate mt-0.5" x-text="c.division"></div>
                                <div class="text-[10px] mt-0.5" :class="c.is_working ? 'text-emerald-700 font-semibold' : 'text-slate-400'">
                                    <span x-text="c.is_working ? ('Masuk ' + c.check_in_time + ' • ' + c.last_seen) : 'Belum masuk kerja'"></span>
                                </div>
                            </button>
                            <button type="button" @click="openQuickAssign(c)"
                                    class="btn btn-primary btn-sm px-2.5 py-1 text-[11px] flex-none"
                                    title="Beri Tugas Dadakan ke Kurir Ini">
                                <x-icon name="zap" class="w-3 h-3" /> Tugas
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Penugasan Dadakan Langsung dari Peta -->
    <div x-show="quickModalOpen" x-cloak
         class="fixed inset-0 z-[9999] bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
        <div @click.outside="quickModalOpen = false"
             class="glass w-full max-w-lg p-6 space-y-4 bg-white border border-slate-300 shadow-2xl rounded-2xl">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <span class="badge badge-orange">Penugasan Cepat via Peta Live</span>
                    <h3 class="text-base font-extrabold text-slate-900 mt-1">
                        Beri Tugas Dadakan ke <span class="text-orange-600" x-text="selectedCourier?.name"></span>
                    </h3>
                    <p class="text-xs text-slate-500" x-text="(selectedCourier?.division || '') + ' • ' + (selectedCourier?.vehicle_name || '')"></p>
                </div>
                <button type="button" @click="quickModalOpen = false" class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>

            <form @submit.prevent="submitQuickAssign()" class="space-y-3.5">
                <div>
                    <label class="label">Pilih Template Cepat (Opsional)</label>
                    <select x-model="selectedTemplateId" @change="applyTemplate()" class="input text-xs">
                        <option value="">-- Ketik Manual Tugas Dadakan --</option>
                        <template x-for="tpl in templates" :key="tpl.id">
                            <option :value="tpl.id" x-text="tpl.title + (tpl.target_division ? ' (' + tpl.target_division + ')' : '')"></option>
                        </template>
                    </select>
                </div>

                <div>
                    <label class="label">Judul Tugas Dadakan (*)</label>
                    <input type="text" x-model="form.title" required placeholder="Contoh: Pickup Dokumen Urgent di Corporate / Pengantaran Paket Segera" class="input">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="label">Titik Gerai / Lokasi Tujuan (*)</label>
                        <select x-model="form.outlet_id" required class="input">
                            @foreach($outlets as $o)
                                <option value="{{ $o->id }}">{{ $o->name }} ({{ $o->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Durasi Target SLA (Menit) (*)</label>
                        <input type="number" x-model="form.estimated_duration_minutes" min="5" max="720" required class="input">
                    </div>
                </div>

                <div>
                    <label class="label">Instruksi / Detail Tugas</label>
                    <textarea x-model="form.description" rows="2" placeholder="Catatan alamat detail, PIC yang dihubungi, atau instruksi khusus..." class="input"></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="quickModalOpen = false" class="btn btn-ghost">Batal</button>
                    <button type="submit" class="btn btn-primary" :disabled="submitting">
                        <x-icon name="zap" class="w-4 h-4" />
                        <span x-text="submitting ? 'Mengirim Tugas...' : 'Kirim Tugas & Push Notifikasi'"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Absent Today & Active Tasks -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 rise rise-4">
    <div class="glass p-5 space-y-3">
        <div class="orn-line mb-1"></div>
        <h2 class="text-base font-extrabold text-white">Belum Presensi Hari Ini ({{ $today['absent']->count() }})</h2>
        <div class="divide-y divide-white/10 max-h-56 overflow-y-auto">
            @forelse($today['absent'] as $ab)
                <div class="flex items-center justify-between py-2.5 text-xs">
                    <div>
                        <div class="font-bold text-white">{{ $ab->name }}</div>
                        <div class="text-slate-400">{{ $ab->specialization }} &bull; {{ $ab->phone ?: $ab->email }}</div>
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
        <div class="divide-y divide-white/10 max-h-56 overflow-y-auto">
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

@push('scripts')
<script>
window.hrdLiveMapController = function () {
    return {
        outlets: @js($map),
        couriers: @js($couriersMap),
        templates: @js($templates),
        filterMode: 'all',
        isFullscreen: false,
        loading: false,
        lastUpdated: '{{ now()->format('H:i:s') }}',
        mapInstance: null,
        outletLayer: null,
        courierLayer: null,
        courierMarkers: {},
        quickModalOpen: false,
        selectedCourier: null,
        selectedTemplateId: '',
        submitting: false,
        form: {
            user_id: '',
            outlet_id: '{{ $outlets->first()?->id ?? '' }}',
            title: '',
            description: '',
            estimated_duration_minutes: 45,
        },
        workingCouriersCount() {
            return this.couriers.filter(c => c.is_working).length;
        },
        filteredCouriers() {
            return this.couriers.filter(c => {
                if (this.filterMode === 'working') return c.is_working;
                if (this.filterMode === 'R4') return c.vehicle_type === 'R4';
                if (this.filterMode === 'R2') return c.vehicle_type === 'R2';
                return true;
            });
        },
        initMap() {
            window.hsmReady(() => {
                const el = document.getElementById('hrd-live-map');
                if (!el || !window.hsmCreateMap) return;
                const center = this.outlets[0] || { lat: -0.502106, lng: 117.153709 };
                this.mapInstance = window.hsmCreateMap(el, center.lat, center.lng, 13);
                this.outletLayer = L.layerGroup().addTo(this.mapInstance);
                this.courierLayer = L.layerGroup().addTo(this.mapInstance);
                this.renderMarkers(true);

                // Global helper agar tombol di dalam Leaflet Popup dapat memanggil modal Alpine
                window.hsmOpenQuickAssignById = (courierId) => {
                    const found = this.couriers.find(x => Number(x.id) === Number(courierId));
                    if (found) {
                        this.openQuickAssign(found);
                    }
                };

                setInterval(() => this.refreshPositions(), 15000);
            });
        },
        renderMarkers(fit = false) {
            if (!this.mapInstance) return;
            this.outletLayer.clearLayers();
            this.courierLayer.clearLayers();
            this.courierMarkers = {};
            const bounds = [];

            // 1. Render Titik Gerai HSM
            this.outlets.forEach((p) => {
                const color = p.status === 'danger' ? '#ef4444' : (p.status === 'orange' ? '#ff6500' : '#10b981');
                L.circle([p.lat, p.lng], { radius: p.radius, color, fillColor: color, fillOpacity: 0.12, weight: 1.5 }).addTo(this.outletLayer);
                L.marker([p.lat, p.lng], { icon: window.hsmPinIcon(color) })
                    .addTo(this.outletLayer)
                    .bindPopup(`
                        <div style="min-width:190px;font-size:12px;line-height:1.45;">
                            <div style="font-weight:800;color:#0f172a;">🏢 ${p.name} (${p.code})</div>
                            <div style="color:#475569;margin-top:2px;">Tugas Hari Ini: <b>${p.done}/${p.tasks}</b> selesai</div>
                            <div style="color:#64748b;font-size:11px;">Radius Geofencing: ${p.radius} m</div>
                        </div>
                    `);
                bounds.push([p.lat, p.lng]);
            });

            // 2. Render Titik Lokasi Kurir
            this.filteredCouriers().forEach((c) => {
                if (!c.lat || !c.lng) return;
                const statusBadge = c.is_working
                    ? `<span style="background:#dcfce7;color:#166534;padding:2px 6px;border-radius:999px;font-size:10px;font-weight:800;">🟢 Masuk Kerja (${c.check_in_time || '-'})</span>`
                    : `<span style="background:#f1f5f9;color:#475569;padding:2px 6px;border-radius:999px;font-size:10px;font-weight:700;">⚪ Belum Aktif / Pulang</span>`;

                const activeTaskHtml = c.active_task
                    ? `<div style="margin-top:6px;padding:5px 7px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;font-size:11px;color:#9a3412;">
                           <b>Sedang Dikerjakan:</b> ${c.active_task.title} (${c.active_task.outlet || '-'})
                       </div>`
                    : '';

                const popupHtml = `
                    <div style="min-width:230px;font-family:'Plus Jakarta Sans Variable',sans-serif;font-size:12px;line-height:1.45;color:#0f172a;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:6px;margin-bottom:4px;">
                            <strong style="font-size:13px;font-weight:800;">${c.name}</strong>
                            ${statusBadge}
                        </div>
                        <div style="font-size:11px;color:#475569;font-weight:600;">${c.division} • ${c.vehicle_name}</div>
                        <div style="font-size:11px;color:#64748b;margin-top:2px;">📞 ${c.phone || '-'}</div>
                        <div style="font-size:11px;color:#334155;margin-top:4px;">
                            📍 <b>${c.location_source}:</b> ${c.last_seen}
                            ${c.accuracy ? ` (±${c.accuracy}m)` : ''}
                        </div>
                        <div style="font-size:11px;color:#334155;margin-top:2px;">
                            📋 Tugas Hari Ini: <b>${c.done_count}/${c.total_tasks}</b> selesai (${c.pending_count} menunggu)
                        </div>
                        ${activeTaskHtml}
                        <button type="button" onclick="window.hsmOpenQuickAssignById(${c.id})"
                                style="margin-top:10px;width:100%;background:linear-gradient(180deg,#ff781f 0%,#ea580c 100%);color:#fff;border:none;padding:7px 10px;border-radius:9px;font-weight:800;font-size:11px;cursor:pointer;box-shadow:0 2px 5px rgba(234,88,12,0.35);">
                            ⚡ Beri Tugas Dadakan ke ${c.name}
                        </button>
                    </div>
                `;

                const marker = L.marker([c.lat, c.lng], { icon: window.hsmCourierPinIcon(c) })
                    .addTo(this.courierLayer)
                    .bindPopup(popupHtml);

                this.courierMarkers[c.id] = marker;
                bounds.push([c.lat, c.lng]);
            });

            if (fit && bounds.length > 1) {
                this.mapInstance.fitBounds(bounds, { padding: [36, 36], maxZoom: 15 });
            }
        },
        focusCourier(c) {
            if (!this.mapInstance) return;
            if (c.lat && c.lng) {
                this.mapInstance.flyTo([c.lat, c.lng], 16, { duration: 0.7 });
                const m = this.courierMarkers[c.id];
                if (m) {
                    setTimeout(() => m.openPopup(), 450);
                }
            } else {
                this.openQuickAssign(c);
            }
        },
        toggleFullscreen() {
            this.isFullscreen = !this.isFullscreen;
            setTimeout(() => {
                if (this.mapInstance) {
                    this.mapInstance.invalidateSize();
                }
            }, 150);
        },
        async refreshPositions() {
            this.loading = true;
            try {
                const res = await fetch('{{ route('hrd.live-couriers') }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    if (data.ok) {
                        this.couriers = data.couriers || [];
                        this.outlets = data.outlets || [];
                        this.lastUpdated = data.updated_at || '';
                        this.renderMarkers(false);
                    }
                }
            } catch (_) {}
            this.loading = false;
        },
        openQuickAssign(courier) {
            this.selectedCourier = courier;
            this.selectedTemplateId = '';
            this.form.user_id = courier.id;
            this.form.title = '';
            this.form.description = '';
            this.form.estimated_duration_minutes = 45;
            this.quickModalOpen = true;
        },
        applyTemplate() {
            if (!this.selectedTemplateId) return;
            const tpl = this.templates.find(t => String(t.id) === String(this.selectedTemplateId));
            if (tpl) {
                this.form.title = tpl.title;
                this.form.description = tpl.description || '';
                if (tpl.default_outlet_id) this.form.outlet_id = tpl.default_outlet_id;
                if (tpl.estimated_duration_minutes) this.form.estimated_duration_minutes = tpl.estimated_duration_minutes;
            }
        },
        async submitQuickAssign() {
            if (!this.form.user_id || !this.form.title) return;
            this.submitting = true;
            try {
                const res = await fetch('{{ route('hrd.tasks.quick-assign') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify(this.form)
                });
                const data = await res.json();
                if (res.ok && data.ok) {
                    this.quickModalOpen = false;
                    if (window.HsmSwal) {
                        window.HsmSwal.toast(data.message, 'success');
                    }
                    await this.refreshPositions();
                } else {
                    const errMsg = data.message || 'Gagal menyimpan penugasan dadakan.';
                    if (window.HsmSwal) window.HsmSwal.toast(errMsg, 'error');
                }
            } catch (e) {
                if (window.HsmSwal) window.HsmSwal.toast('Terjadi kesalahan jaringan.', 'error');
            }
            this.submitting = false;
        }
    };
};
</script>
@endpush
@endsection
