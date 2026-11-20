@extends('layouts.app', ['title' => $outlet->exists ? 'Edit Gerai & Corporate' : 'Tambah Gerai / Corporate Baru', 'subtitle' => 'Geser pin pada peta untuk menentukan koordinat GPS dan radius geofencing'])

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="glass p-6">
        <div class="orn-line mb-2"></div>
        <h2 class="text-base font-extrabold text-white mb-4">
            {{ $outlet->exists ? 'Perbarui Lokasi: '.$outlet->name : 'Formulir Data Gerai & Corporate Baru' }}
        </h2>

        <form method="POST" action="{{ $outlet->exists ? route('hrd.outlets.update', $outlet) : route('hrd.outlets.store') }}" class="space-y-4">
            @csrf
            @if($outlet->exists) @method('PUT') @endif

            <!-- Pilih Tipe Lokasi -->
            <div>
                <label class="label">Kategori / Tipe Lokasi (*)</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <label class="flex items-start gap-3 p-3 rounded-2xl border cursor-pointer transition"
                           :class="locType === 'gerai' ? 'bg-cyan-500/10 border-cyan-500/40 text-cyan-200' : 'bg-white/5 border-white/10 text-slate-300 hover:border-white/20'"
                           x-data="{ locType: '{{ old('type', $outlet->type ?? \App\Models\Outlet::TYPE_GERAI) }}' }">
                        <input type="radio" name="type" value="gerai" @checked(old('type', $outlet->type ?? \App\Models\Outlet::TYPE_GERAI) === \App\Models\Outlet::TYPE_GERAI) class="mt-0.5">
                        <div class="min-w-0">
                            <div class="font-bold text-xs text-white flex items-center gap-1.5">
                                <span>🏪</span> Gerai Cabang
                            </div>
                            <div class="text-[11px] text-slate-400 mt-0.5 leading-snug">Titik gerai/cabang TIKISMD operasional pickup & delivery paket.</div>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3 rounded-2xl border cursor-pointer transition"
                           :class="locType === 'corporate' ? 'bg-purple-500/10 border-purple-500/40 text-purple-200' : 'bg-white/5 border-white/10 text-slate-300 hover:border-white/20'"
                           x-data="{ locType: '{{ old('type', $outlet->type ?? \App\Models\Outlet::TYPE_GERAI) }}' }">
                        <input type="radio" name="type" value="corporate" @checked(old('type', $outlet->type) === \App\Models\Outlet::TYPE_CORPORATE) class="mt-0.5">
                        <div class="min-w-0">
                            <div class="font-bold text-xs text-white flex items-center gap-1.5">
                                <span>🏢</span> Perusahaan Rekan (Corporate)
                            </div>
                            <div class="text-[11px] text-slate-400 mt-0.5 leading-snug">Perusahaan/kantor mitra kerja sama B2B untuk jadwal penjemputan paket.</div>
                        </div>
                    </label>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="label">Kode Lokasi (*)</label>
                    <input type="text" name="code" value="{{ old('code', $outlet->code) }}" placeholder="Contoh: CORP-001 / GR-004" required class="input font-mono uppercase">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Nama Gerai / Perusahaan Rekan (*)</label>
                    <input type="text" name="name" value="{{ old('name', $outlet->name) }}" placeholder="Contoh: PT. Sumber Makmur / Gerai Antasari" required class="input">
                </div>
            </div>

            <div>
                <label class="label">Alamat Lengkap (*)</label>
                <textarea name="address" rows="2" placeholder="Tuliskan nama jalan, kelurahan, kecamatan di Samarinda..." required class="input">{{ old('address', $outlet->address) }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="label">Latitude</label>
                    <input id="inp-lat" type="number" step="0.00000001" name="latitude" value="{{ old('latitude', $outlet->latitude) }}" required class="input font-mono">
                </div>
                <div>
                    <label class="label">Longitude</label>
                    <input id="inp-lng" type="number" step="0.00000001" name="longitude" value="{{ old('longitude', $outlet->longitude) }}" required class="input font-mono">
                </div>
                <div>
                    <label class="label">Radius Geofence (m)</label>
                    <input id="inp-rad" type="number" min="10" max="1000" name="radius_meters" value="{{ old('radius_meters', $outlet->radius_meters ?? 50) }}" required class="input">
                </div>
            </div>

            <div class="flex flex-col sm:flex-row gap-4 pt-1">
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_main_office" value="1" @checked(old('is_main_office', $outlet->is_main_office)) class="check">
                    <span class="text-xs font-semibold text-white">Jadikan Kantor Utama (Titik Presensi Wajib)</span>
                </label>
                <label class="inline-flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $outlet->is_active ?? true)) class="check">
                    <span class="text-xs font-semibold text-white">Status Lokasi Aktif</span>
                </label>
            </div>

            <div class="flex items-center justify-end gap-2 pt-3">
                <a href="{{ route('hrd.outlets.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Data Lokasi</button>
            </div>
        </form>
    </div>

    <!-- Interactive Map Picker -->
    <div class="glass p-6 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <div class="orn-line mb-1"></div>
                <h3 class="text-base font-extrabold text-white">Pemilih Titik Lokasi Interaktif (Kota Samarinda)</h3>
                <p class="text-xs text-slate-400">Klik pada peta atau seret pin untuk mengambil koordinat presisi di Kota Samarinda.</p>
            </div>
            <button type="button" id="btn-my-loc" class="btn btn-ghost btn-sm">Gunakan GPS Saya</button>
        </div>

        <div class="flex flex-wrap gap-1.5 text-[11px]">
            <button type="button" data-lat="-0.502106" data-lng="117.153709" class="samarinda-preset btn btn-ghost btn-sm text-[11px]">Pusat Kota (Gajah Mada)</button>
            <button type="button" data-lat="-0.478920" data-lng="117.141550" class="samarinda-preset btn btn-ghost btn-sm text-[11px]">Samarinda Ulu (Juanda)</button>
            <button type="button" data-lat="-0.507850" data-lng="117.163400" class="samarinda-preset btn btn-ghost btn-sm text-[11px]">Samarinda Ilir</button>
            <button type="button" data-lat="-0.517640" data-lng="117.139820" class="samarinda-preset btn btn-ghost btn-sm text-[11px]">Samarinda Seberang</button>
            <button type="button" data-lat="-0.472850" data-lng="117.169420" class="samarinda-preset btn btn-ghost btn-sm text-[11px]">Sungai Pinang</button>
        </div>

        <div id="outlet-picker-map" class="h-96 rounded-2xl overflow-hidden border border-white/10"></div>
    </div>
</div>

@push('scripts')
<script>
window.hsmReady(() => {
    const latInput = document.getElementById('inp-lat');
    const lngInput = document.getElementById('inp-lng');
    const radInput = document.getElementById('inp-rad');
    const mapEl = document.getElementById('outlet-picker-map');
    if (!mapEl || !window.hsmCreateMap) return;

    const initLat = parseFloat(latInput.value) || -0.502106;
    const initLng = parseFloat(lngInput.value) || 117.153709;
    const initRad = parseInt(radInput.value, 10) || 50;

    const map = window.hsmCreateMap(mapEl, initLat, initLng, 16);
    const marker = L.marker([initLat, initLng], { draggable: true, icon: window.hsmPinIcon('#ff6500') }).addTo(map);
    const circle = L.circle([initLat, initLng], { radius: initRad, color: '#00d2ff', fillColor: '#00d2ff', fillOpacity: 0.18 }).addTo(map);

    function sync(lat, lng, pan = false) {
        latInput.value = Number(lat).toFixed(8);
        lngInput.value = Number(lng).toFixed(8);
        marker.setLatLng([lat, lng]);
        circle.setLatLng([lat, lng]);
        if (pan) map.setView([lat, lng], 16);
    }

    marker.on('dragend', () => {
        const p = marker.getLatLng();
        sync(p.lat, p.lng);
    });
    map.on('click', (e) => sync(e.latlng.lat, e.latlng.lng));
    [latInput, lngInput].forEach((inp) => inp.addEventListener('change', () => {
        sync(parseFloat(latInput.value) || initLat, parseFloat(lngInput.value) || initLng, true);
    }));
    radInput.addEventListener('input', () => circle.setRadius(parseInt(radInput.value, 10) || 50));

    document.querySelectorAll('.samarinda-preset').forEach((btn) => {
        btn.addEventListener('click', () => {
            sync(parseFloat(btn.dataset.lat), parseFloat(btn.dataset.lng), true);
        });
    });

    document.getElementById('btn-my-loc')?.addEventListener('click', () => {
        if (!navigator.geolocation) return;
        navigator.geolocation.getCurrentPosition((pos) => {
            sync(pos.coords.latitude, pos.coords.longitude, true);
        });
    });
});
</script>
@endpush
@endsection
