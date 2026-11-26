@extends('layouts.app', ['title' => 'Monitoring Presensi Terintegrasi HSM', 'subtitle' => 'Data jam masuk, jam pulang, radius kantor, dan foto selfie wajah langsung dari Portal Absensi HSM'])

@section('content')
<div x-data="{
    preview: null,
    openPhoto(url, name, label, time, meta) {
        this.preview = { url, name, label, time, meta };
    },
    closePhoto() {
        this.preview = null;
    }
}" @keydown.escape.window="closePhoto()" class="space-y-5">

    <!-- Filter Bar & Portal Sync Info -->
    <div class="glass p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" action="{{ route('hrd.attendances.index') }}" class="flex flex-wrap items-center gap-2">
            <label for="att-date" class="text-xs font-bold text-slate-700">Tanggal Kerja:</label>
            <input id="att-date" type="date" name="date" value="{{ $date->toDateString() }}" class="input w-auto min-h-11">
            <button type="submit" class="btn btn-primary btn-sm min-h-11 px-4">Tampilkan Data</button>
        </form>

        <div class="flex flex-wrap items-center gap-2">
            <span class="badge badge-success">Sumber Live: Portal Absensi HSM</span>
            <a href="{{ $portalUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-ghost btn-sm min-h-11">
                <x-icon name="external-link" class="w-3.5 h-3.5" /> Buka Portal tikismd.id
            </a>
        </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 rise">
        <div class="glass p-3.5">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Hadir Masuk</div>
            <div class="mt-1 text-2xl font-extrabold text-emerald-600">{{ $stats['present'] }}</div>
            <div class="text-[11px] text-slate-400">Kurir aktif bekerja</div>
        </div>
        <div class="glass p-3.5">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Tepat Waktu</div>
            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $stats['on_time'] }}</div>
            <div class="text-[11px] text-emerald-700 font-semibold">Sesuai jadwal shift</div>
        </div>
        <div class="glass p-3.5">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Terlambat</div>
            <div class="mt-1 text-2xl font-extrabold text-amber-600">{{ $stats['late'] }}</div>
            <div class="text-[11px] text-slate-400">Melewati jam shift</div>
        </div>
        <div class="glass p-3.5">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Luar Radius / Ditolak</div>
            <div class="mt-1 text-2xl font-extrabold {{ $stats['invalid'] > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $stats['invalid'] }}</div>
            <div class="text-[11px] text-slate-400">Validasi geofence HSM</div>
        </div>
        <div class="glass p-3.5">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Sudah Pulang</div>
            <div class="mt-1 text-2xl font-extrabold text-slate-900">{{ $stats['checked_out'] }}</div>
            <div class="text-[11px] text-slate-400">Tercatat check-out</div>
        </div>
        <div class="glass p-3.5">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Belum Presensi</div>
            <div class="mt-1 text-2xl font-extrabold text-amber-700">{{ $stats['absent'] }}</div>
            <div class="text-[11px] text-slate-400">{{ $stats['non_working'] }} cuti / izin</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Main Integrated Attendance Table -->
        <div class="lg:col-span-2 table-surface">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <div class="orn-line mb-1"></div>
                    <h2 class="text-base font-extrabold text-white">Daftar Presensi Kurir ({{ $date->translatedFormat('d F Y') }})</h2>
                </div>
                <span class="text-xs text-slate-500">Klik foto selfie untuk memperbesar</span>
            </div>

            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th>Karyawan & Kantor</th>
                            <th>Foto Selfie</th>
                            <th>Jam Masuk & Pulang</th>
                            <th>Radius Kantor</th>
                            <th>Status & Validasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $a)
                            @php
                                $inUrl = $a->checkInPhotoUrl();
                                $outUrl = $a->checkOutPhotoUrl();
                                $inRadius = $a->isWithinCheckInRadius();
                            @endphp
                            <tr>
                                <td data-card-header="1" data-label="Karyawan & Kantor">
                                    <div class="flex items-start justify-between w-full gap-2">
                                        <div>
                                            <div class="font-extrabold text-white text-sm">{{ $a->user?->name }}</div>
                                            <div class="font-mono text-[11px] text-orange-700 font-semibold">{{ $a->user?->nik }} @if($a->hsm_nik) &bull; {{ $a->hsm_nik }} @endif</div>
                                            <div class="text-[11px] text-slate-600 mt-0.5 font-medium">
                                                {{ $a->officeDisplayName() }}
                                                @if($a->shift_name)
                                                    &bull; Shift {{ $a->shift_name }}
                                                @endif
                                            </div>
                                        </div>
                                        <span class="badge {{ $a->statusBadge() }} md:hidden">{{ $a->statusLabel() }}</span>
                                    </div>
                                </td>

                                <td data-label="Foto Selfie Wajah">
                                    <div class="flex items-center gap-2">
                                        @if($inUrl)
                                            <button type="button"
                                                    @click="openPhoto(@js($inUrl), @js($a->user?->name), 'Selfie Presensi Masuk', @js(($a->check_in_at?->format('H:i:s') ?? '-').' WITA'), @js($a->officeDisplayName().' • '.$a->radiusSummary()))"
                                                    class="group relative w-11 h-11 rounded-xl overflow-hidden border border-emerald-300 bg-slate-100 shadow-xs focus:outline-none focus:ring-2 focus:ring-orange-500"
                                                    title="Lihat Selfie Masuk {{ $a->user?->name }}"
                                                    aria-label="Lihat foto selfie masuk {{ $a->user?->name }}">
                                                <img src="{{ $inUrl }}" alt="Selfie masuk {{ $a->user?->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition">
                                                <span class="absolute bottom-0 inset-x-0 bg-slate-900/75 text-[9px] font-bold text-white text-center py-0.5">IN</span>
                                            </button>
                                        @else
                                            <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-400" title="Tidak ada foto masuk">No IN</span>
                                        @endif

                                        @if($outUrl)
                                            <button type="button"
                                                    @click="openPhoto(@js($outUrl), @js($a->user?->name), 'Selfie Presensi Pulang', @js(($a->check_out_at?->format('H:i:s') ?? '-').' WITA'), @js($a->officeDisplayName().' • Pulang '.($a->formattedDistance($a->check_out_distance_meters !== null ? (float) $a->check_out_distance_meters : null))))"
                                                    class="group relative w-11 h-11 rounded-xl overflow-hidden border border-slate-300 bg-slate-100 shadow-xs focus:outline-none focus:ring-2 focus:ring-orange-500"
                                                    title="Lihat Selfie Pulang {{ $a->user?->name }}"
                                                    aria-label="Lihat foto selfie pulang {{ $a->user?->name }}">
                                                <img src="{{ $outUrl }}" alt="Selfie pulang {{ $a->user?->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition">
                                                <span class="absolute bottom-0 inset-x-0 bg-slate-900/75 text-[9px] font-bold text-white text-center py-0.5">OUT</span>
                                            </button>
                                        @elseif($a->check_out_at)
                                            <span class="inline-flex items-center justify-center w-11 h-11 rounded-xl border border-slate-200 bg-slate-50 text-[10px] font-bold text-slate-400">No OUT</span>
                                        @endif
                                    </div>
                                </td>

                                <td data-label="Jam Masuk & Pulang" class="text-xs">
                                    <div class="font-mono font-bold text-slate-900">Masuk: {{ $a->check_in_at?->format('H:i:s') ?? '-' }} WITA</div>
                                    <div class="font-mono text-slate-600 mt-0.5">
                                        Pulang: {{ $a->check_out_at ? $a->check_out_at->format('H:i:s').' WITA' : 'Belum Pulang' }}
                                    </div>
                                    @if($a->workDurationFormatted())
                                        <div class="text-[11px] text-slate-500 mt-0.5">Durasi: {{ $a->workDurationFormatted() }}</div>
                                    @endif
                                </td>

                                <td data-label="Radius Kantor & Jarak" class="text-xs">
                                    <div class="font-bold text-slate-900">
                                        {{ $a->formattedDistance($a->check_in_distance_meters !== null ? (float) $a->check_in_distance_meters : null) }}
                                        <span class="font-normal text-slate-500">/ Radius {{ $a->officeRadiusMeters() }} m</span>
                                    </div>
                                    <div class="mt-0.5">
                                        @if($inRadius)
                                            <span class="text-[11px] font-bold text-emerald-700">Dalam Radius Kantor</span>
                                        @else
                                            <span class="text-[11px] font-bold text-red-700">Di Luar Radius Kantor</span>
                                        @endif
                                    </div>
                                    @if($a->check_out_distance_meters !== null)
                                        <div class="text-[11px] text-slate-500 mt-0.5">
                                            Jarak Pulang: {{ $a->formattedDistance((float) $a->check_out_distance_meters) }}
                                        </div>
                                    @endif
                                </td>

                                <td data-label="Status & Validasi" class="text-xs">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="badge {{ $a->statusBadge() }}">{{ $a->statusLabel() }}</span>
                                        <span class="badge {{ $a->validationBadge() }}">{{ $a->validationLabel() }}</span>
                                    </div>
                                    <div class="mt-1 {{ $a->late_minutes > 0 ? 'text-amber-700 font-semibold' : 'text-emerald-700 font-medium' }}">
                                        {{ $a->late_minutes > 0 ? 'Terlambat '.$a->late_minutes.' menit' : 'Tepat waktu' }}
                                    </div>
                                    @if($a->notes)
                                        <div class="text-[11px] text-slate-600 mt-0.5">{{ $a->notes }}</div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-slate-500 py-8">
                                    Belum ada data presensi masuk kurir di Portal Absensi HSM pada tanggal {{ $date->translatedFormat('d F Y') }}.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Side Panels: Leave/Excused & Not Yet Checked In -->
        <div class="space-y-5">
            @if($nonWorking->isNotEmpty())
                <div class="glass p-5 space-y-3">
                    <div class="orn-line mb-1"></div>
                    <h3 class="text-base font-extrabold text-white">Cuti / Izin / Libur ({{ $nonWorking->count() }})</h3>
                    <div class="divide-y divide-slate-200">
                        @foreach($nonWorking as $nw)
                            <div class="py-2.5 flex items-start justify-between gap-2 text-xs">
                                <div class="min-w-0">
                                    <div class="font-bold text-slate-900">{{ $nw->user?->name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ $nw->user?->specialization }}</div>
                                    @if($nw->notes)
                                        <div class="text-[11px] text-slate-600 mt-0.5">{{ $nw->notes }}</div>
                                    @endif
                                </div>
                                <span class="badge {{ $nw->statusBadge() }} flex-none">{{ $nw->statusLabel() }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="glass p-5 space-y-3">
                <div class="orn-line mb-1"></div>
                <h3 class="text-base font-extrabold text-white">Belum Presensi ({{ $absent->count() }})</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Seluruh pencatatan presensi masuk, presensi pulang, validasi radius kantor, dan koreksi kehadiran dilakukan terpusat di Portal Absensi HSM.
                </p>
                <div class="divide-y divide-slate-200 max-h-80 overflow-y-auto">
                    @forelse($absent as $ab)
                        <div class="py-2.5 flex items-center justify-between gap-2 text-xs">
                            <div class="min-w-0">
                                <div class="font-bold text-slate-900 truncate">{{ $ab->name }}</div>
                                <div class="text-[11px] text-slate-500 truncate">{{ $ab->specialization }} &bull; {{ $ab->phone }}</div>
                            </div>
                            <span class="badge badge-muted flex-none">Belum Absen</span>
                        </div>
                    @empty
                        <div class="py-4 text-center text-xs font-semibold text-emerald-700">
                            Seluruh kurir aktif telah memiliki status kehadiran pada tanggal ini.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Selfie Photo Preview Modal -->
    <div x-show="preview !== null" x-cloak
         class="fixed inset-0 z-[9999] bg-slate-900/75 backdrop-blur-xs flex items-center justify-center p-4"
         role="dialog" aria-modal="true" aria-label="Pratinjau Foto Selfie Presensi">
        <div @click.outside="closePhoto()" class="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden">
            <div class="p-4 border-b border-slate-200 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="text-xs font-bold uppercase tracking-wider text-orange-700" x-text="preview?.label"></div>
                    <h3 class="text-base font-extrabold text-slate-900 truncate mt-0.5" x-text="preview?.name"></h3>
                    <p class="text-xs text-slate-600 mt-0.5">
                        <span class="font-mono font-bold text-slate-800" x-text="preview?.time"></span>
                        &bull; <span x-text="preview?.meta"></span>
                    </p>
                </div>
                <button type="button" @click="closePhoto()" class="btn btn-ghost btn-sm min-h-11 min-w-11 p-2" aria-label="Tutup pratinjau foto">
                    <x-icon name="x" class="w-4 h-4" />
                </button>
            </div>
            <div class="bg-slate-950 flex items-center justify-center max-h-[70dvh] overflow-hidden">
                <template x-if="preview">
                    <img :src="preview.url" :alt="preview.label + ' ' + preview.name" class="max-h-[70dvh] w-auto object-contain">
                </template>
            </div>
        </div>
    </div>
</div>
@endsection
