@extends('layouts.app', ['title' => 'Data Master Gerai & Kantor Utama', 'subtitle' => 'Titik koordinat lokasi pengerjaan dan geofencing radius GPS'])

@section('content')
<div class="glass p-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('hrd.outlets.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari kode, nama, alamat gerai..." class="input sm:max-w-xs">
        <select name="status" class="input sm:w-40">
            <option value="">Semua Status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="btn btn-ghost">Filter</button>
    </form>
    @if(auth()->user()->canManage())
        <a href="{{ route('hrd.outlets.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="w-4 h-4" /> Tambah Gerai Baru
        </a>
    @endif
</div>

<div class="glass p-5 space-y-4">
    <div id="outlets-overview-map" class="h-64 rounded-2xl overflow-hidden border border-white/10"></div>

    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Nama Gerai & Alamat</th>
                    <th>Koordinat GPS</th>
                    <th>Radius</th>
                    <th>Tipe</th>
                    <th>Status</th>
                    @if(auth()->user()->canManage())
                        <th class="text-right">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($outlets as $o)
                    <tr>
                        <td class="font-mono font-bold text-orange-300">{{ $o->code }}</td>
                        <td>
                            <div class="font-bold text-white">{{ $o->name }}</div>
                            <div class="text-xs text-slate-400">{{ $o->address }}</div>
                        </td>
                        <td class="font-mono text-xs text-slate-300">{{ number_format($o->latitude, 6) }}, {{ number_format($o->longitude, 6) }}</td>
                        <td><span class="badge badge-info">{{ $o->radius_meters }} m</span></td>
                        <td>
                            @if($o->is_main_office)
                                <span class="badge badge-orange">Kantor Utama Presensi</span>
                            @else
                                <span class="badge badge-muted">Gerai Operasional ({{ $o->tasks_count }} tugas)</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $o->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $o->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        @if(auth()->user()->canManage())
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('hrd.outlets.edit', $o) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    @if(!$o->is_main_office)
                                        <form method="POST" action="{{ route('hrd.outlets.destroy', $o) }}"
                                              data-confirm="Hapus gerai {{ $o->name }}?">
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
                    <tr><td colspan="7" class="text-center text-slate-400 py-8">Belum ada data gerai.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div>{{ $outlets->links() }}</div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const items = @json($mapOutlets);
    const el = document.getElementById('outlets-overview-map');
    if (!el || !window.hsmCreateMap) return;
    const first = items[0] || { latitude: -0.5022, longitude: 117.1536 };
    const map = window.hsmCreateMap(el, first.latitude, first.longitude, 13);
    const bounds = [];
    items.forEach((o) => {
        const color = o.is_main_office ? '#ff6500' : '#00d2ff';
        L.circle([o.latitude, o.longitude], { radius: o.radius_meters, color, fillColor: color, fillOpacity: 0.16 }).addTo(map);
        L.marker([o.latitude, o.longitude], { icon: window.hsmPinIcon(color) })
            .addTo(map)
            .bindPopup(`<b>${o.name}</b> (${o.code})<br>${o.address}<br>Radius: ${o.radius_meters} m`);
        bounds.push([o.latitude, o.longitude]);
    });
    if (bounds.length > 1) map.fitBounds(bounds, { padding: [25, 25] });
});
</script>
@endpush
@endsection
