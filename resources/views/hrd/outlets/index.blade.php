@extends('layouts.app', ['title' => 'Data Master Gerai & Corporate', 'subtitle' => 'Titik koordinat lokasi pengerjaan gerai cabang, perusahaan rekanan (corporate), dan kantor utama'])

@section('content')
<!-- Summary Stats Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
    <div class="glass p-4 flex items-center justify-between">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Titik Lokasi</div>
            <div class="text-2xl font-black text-white mt-0.5">{{ $stats['total'] }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-orange-400">
            <x-icon name="pin" class="w-5 h-5" />
        </div>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-cyan-400">Gerai Cabang</div>
            <div class="text-2xl font-black text-cyan-400 mt-0.5">{{ $stats['gerai'] }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-400">
            <span class="text-lg">🏪</span>
        </div>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-purple-400">Perusahaan Corporate</div>
            <div class="text-2xl font-black text-purple-400 mt-0.5">{{ $stats['corporate'] }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400">
            <span class="text-lg">🏢</span>
        </div>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <div>
            <div class="text-[11px] font-bold uppercase tracking-wider text-orange-400">Kantor Utama</div>
            <div class="text-2xl font-black text-orange-400 mt-0.5">{{ $stats['main_office'] }}</div>
        </div>
        <div class="w-10 h-10 rounded-xl bg-orange-500/10 border border-orange-500/20 flex items-center justify-center text-orange-400">
            <span class="text-lg">⭐</span>
        </div>
    </div>
</div>

<!-- Filter & Actions -->
<div class="glass p-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('hrd.outlets.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari kode, nama gerai atau corporate..." class="input sm:max-w-xs">
        <select name="type" class="input sm:w-44">
            <option value="">Semua Tipe Lokasi</option>
            <option value="gerai" @selected(request('type') === 'gerai')>🏪 Gerai Cabang</option>
            <option value="corporate" @selected(request('type') === 'corporate')>🏢 Corporate (Rekan)</option>
            <option value="main_office" @selected(request('type') === 'main_office')>⭐ Kantor Utama</option>
        </select>
        <select name="status" class="input sm:w-36">
            <option value="">Semua Status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="btn btn-ghost">Filter</button>
        @if(request()->anyFilled(['q', 'type', 'status']))
            <a href="{{ route('hrd.outlets.index') }}" class="btn btn-ghost text-xs text-slate-400">Reset</a>
        @endif
    </form>
    @if(auth()->user()->canManage())
        <a href="{{ route('hrd.outlets.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="w-4 h-4" /> Tambah Gerai / Corporate
        </a>
    @endif
</div>

<!-- Map Overview -->
<div class="glass p-4 space-y-2">
    <div class="flex items-center justify-between text-xs px-1 text-slate-300">
        <span class="font-bold flex items-center gap-1.5"><x-icon name="pin" class="w-3.5 h-3.5 text-orange-400" /> Peta Sebaran Gerai & Perusahaan Rekan (Samarinda)</span>
        <div class="flex items-center gap-3 text-[11px]">
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span> Kantor Utama</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-cyan-400"></span> Gerai Cabang</span>
            <span class="inline-flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span> Corporate (Rekan)</span>
        </div>
    </div>
    <div id="outlets-overview-map" class="h-64 rounded-2xl overflow-hidden border border-white/10"></div>
</div>

<!-- Table -->
<div class="table-surface">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Tipe Lokasi</th>
                    <th>Nama & Alamat Lengkap</th>
                    <th>Koordinat GPS</th>
                    <th>Radius Geofence</th>
                    <th>Beban Tugas</th>
                    <th>Status</th>
                    @if(auth()->user()->canManage())
                        <th class="text-right">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($outlets as $o)
                    <tr>
                        <td data-card-header="1" data-label="Identitas Lokasi">
                            <div class="flex items-center justify-between w-full gap-2">
                                <div>
                                    <span class="font-mono font-extrabold text-orange-300 text-xs">{{ $o->code }}</span>
                                    <div class="font-extrabold text-white text-sm md:hidden mt-0.5">{{ $o->name }}</div>
                                </div>
                                <span class="badge {{ $o->is_active ? 'badge-success' : 'badge-danger' }} md:hidden">
                                    {{ $o->is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                        </td>
                        <td data-label="Tipe Lokasi">
                            @if($o->is_main_office)
                                <span class="badge badge-orange font-bold">⭐ Kantor Utama</span>
                            @elseif($o->isCorporate())
                                <span class="badge badge-primary font-bold" style="background: rgba(168, 85, 247, 0.2); color: #c084fc; border-color: rgba(168, 85, 247, 0.3);">🏢 Corporate Rekan</span>
                            @else
                                <span class="badge badge-info font-bold">🏪 Gerai Cabang</span>
                            @endif
                        </td>
                        <td data-label="Nama & Alamat Lengkap">
                            <div>
                                <div class="font-bold text-white hidden md:block">{{ $o->name }}</div>
                                <div class="text-xs text-slate-300">{{ $o->address }}</div>
                            </div>
                        </td>
                        <td data-label="Koordinat GPS (Samarinda)" class="font-mono text-xs text-slate-300">
                            {{ number_format($o->latitude, 6) }}, {{ number_format($o->longitude, 6) }}
                        </td>
                        <td data-label="Radius Geofence"><span class="badge badge-info">{{ $o->radius_meters }} meter</span></td>
                        <td data-label="Beban Tugas">
                            @if($o->is_main_office)
                                <span class="badge badge-orange">Titik Presensi Utama</span>
                            @else
                                <span class="badge badge-muted">{{ $o->tasks_count }} penugasan</span>
                            @endif
                        </td>
                        <td data-label="Status">
                            <span class="badge {{ $o->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $o->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        @if(auth()->user()->canManage())
                            <td data-card-actions="1" data-label="Aksi" class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('hrd.outlets.edit', $o) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    @if(!$o->is_main_office)
                                        <form method="POST" action="{{ route('hrd.outlets.destroy', $o) }}"
                                              data-confirm="Hapus {{ $o->typeLabel() }} {{ $o->name }}?">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-400 py-8">Belum ada data gerai atau corporate.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $outlets->links() }}</div>
</div>

@push('scripts')
<script>
window.hsmReady(() => {
    const items = @json($mapOutlets);
    const el = document.getElementById('outlets-overview-map');
    if (!el || !window.hsmCreateMap) return;
    const first = items[0] || { latitude: -0.502106, longitude: 117.153709 };
    const map = window.hsmCreateMap(el, first.latitude, first.longitude, 13);
    const bounds = [];
    items.forEach((o) => {
        let color = '#00d2ff'; // default Gerai
        let typeStr = 'Gerai Cabang';
        if (o.is_main_office) {
            color = '#ff6500';
            typeStr = 'Kantor Utama Presensi';
        } else if (o.type === 'corporate') {
            color = '#a855f7';
            typeStr = 'Perusahaan Rekan (Corporate)';
        }

        L.circle([o.latitude, o.longitude], { radius: o.radius_meters, color, fillColor: color, fillOpacity: 0.18, weight: 2 }).addTo(map);
        L.marker([o.latitude, o.longitude], { icon: window.hsmPinIcon(color) })
            .addTo(map)
            .bindPopup(`<b>${o.name}</b> (${o.code})<br><span style="color:${color};font-weight:bold;">[${typeStr}]</span><br>${o.address}<br>Radius: ${o.radius_meters} m`);
        bounds.push([o.latitude, o.longitude]);
    });
    if (bounds.length > 1) map.fitBounds(bounds, { padding: [28, 28], maxZoom: 15 });
});
</script>
@endpush
@endsection
