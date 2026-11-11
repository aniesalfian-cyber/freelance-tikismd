@extends('layouts.app', ['title' => 'Pengelompokan Tim Karyawan Berdasarkan Divisi', 'subtitle' => 'Dikelompokkan ke dalam 4 Divisi Operasional (R2 Sepeda Motor & R4 Mobil Box)'])

@section('content')
<!-- Filter & Header Actions -->
<div class="glass p-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('hrd.freelancers.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari nama, No. HP, NIK..." class="input sm:max-w-xs">
        <select name="division" class="input sm:w-64">
            <option value="">Semua 4 Divisi</option>
            @foreach($divisions as $dKey => $dData)
                <option value="{{ $dKey }}" @selected(request('division') === $dKey)>{{ $dData['name'] }} ({{ $dData['vehicle_short'] }})</option>
            @endforeach
        </select>
        <select name="status" class="input sm:w-36">
            <option value="">Semua Status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="btn btn-ghost">Filter</button>
        @if(request()->anyFilled(['q', 'division', 'status']))
            <a href="{{ route('hrd.freelancers.index') }}" class="btn btn-ghost text-xs">Reset</a>
        @endif
    </form>
    @if(auth()->user()->canManage())
        <a href="{{ route('hrd.freelancers.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="w-4 h-4" /> Daftarkan Karyawan
        </a>
    @endif
</div>

<!-- Executive Summary Banner -->
<div class="glass p-5 border-l-4 border-l-orange-500 space-y-2">
    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div>
            <h2 class="text-base font-black text-slate-900 tracking-tight">PENGELOMPOKAN TIM BERDASARKAN DIVISI</h2>
            <p class="text-xs text-slate-600 mt-0.5">Dikelompokkan ke dalam 4 Divisi Operasional sesuai moda armada kendaraan & lingkup tugas kurir.</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="badge badge-orange font-bold">{{ $freelancers->count() }} Total Personel Kurir</span>
            <span class="badge badge-muted">Kota Samarinda</span>
        </div>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 pt-2 text-xs">
        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
            <div class="font-extrabold text-sky-700">R4 Kurier Delivery</div>
            <div class="text-[11px] text-slate-500">Mobil Box &bull; 1 Personel</div>
        </div>
        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
            <div class="font-extrabold text-orange-700">R4 Gerai, Corp & Deliv</div>
            <div class="text-[11px] text-slate-500">Mobil Box &bull; 2 Personel</div>
        </div>
        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
            <div class="font-extrabold text-emerald-700">R2 Delivery</div>
            <div class="text-[11px] text-slate-500">Sepeda Motor &bull; 10 Personel</div>
        </div>
        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
            <div class="font-extrabold text-amber-700">R2 Pickup Corporate</div>
            <div class="text-[11px] text-slate-500">Sepeda Motor &bull; 4 Personel</div>
        </div>
    </div>
</div>

<!-- Grouped Division Tables & Cards -->
<div class="space-y-6">
    @foreach($divisions as $divName => $divMeta)
        @php
            $groupMembers = $grouped->get($divName, collect());
            // Skip jika difilter dan divisi tidak cocok
            if (request('division') && request('division') !== $divName) {
                continue;
            }
        @endphp

        <div class="paper-card overflow-hidden">
            <!-- Division Header Plate (Styled after Excel Specification) -->
            <div class="px-5 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3"
                 style="background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);">
                <div class="space-y-1">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="badge {{ $divMeta['badge'] }} text-xs font-black uppercase tracking-wider">
                            {{ $divMeta['name'] }}
                        </span>
                        <span class="badge badge-muted font-bold">
                            {{ $groupMembers->count() }} Personel
                        </span>
                    </div>
                    <div class="text-xs text-slate-600 flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-slate-900">Kendaraan: {{ $divMeta['vehicle'] }}</span>
                        <span>&bull;</span>
                        <span>Lingkup Tugas: <b class="text-slate-800">{{ $divMeta['scope'] }}</b></span>
                    </div>
                </div>
                <div class="flex items-center gap-1.5 text-xs text-slate-500">
                    <span class="w-2.5 h-2.5 rounded-full {{ str_starts_with($divMeta['vehicle_type'], 'R4') ? 'bg-orange-500' : 'bg-emerald-500' }}"></span>
                    <span class="font-bold">{{ $divMeta['vehicle_type'] }} Armada</span>
                </div>
            </div>

            <!-- Division Members Table -->
            <div class="tbl-wrap">
                <table class="tbl">
                    <thead>
                        <tr>
                            <th class="w-12 text-center">No</th>
                            <th>Nama Personel</th>
                            <th>Nomor Handphone (Login)</th>
                            <th>NIK</th>
                            <th>Kendaraan</th>
                            <th>Tugas (30 Hari)</th>
                            <th>Rata-rata KPI</th>
                            <th>Status</th>
                            @if(auth()->user()->canManage())
                                <th class="text-right">Aksi</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($groupMembers as $idx => $fl)
                            @php
                                $kpi = round((float) $fl->kpi_30d, 2);
                                $grade = \App\Services\PerformanceScoringService::gradeFor($kpi);
                            @endphp
                            <tr>
                                <td data-label="No" class="text-center font-bold text-slate-500">
                                    {{ $idx + 1 }}
                                </td>
                                <td data-card-header="1" data-label="Nama Personel">
                                    <div class="flex items-center justify-between w-full gap-2">
                                        <div>
                                            <div class="font-extrabold text-slate-900 text-sm">{{ $fl->name }}</div>
                                            <div class="text-[11px] text-slate-500">{{ $fl->email }}</div>
                                        </div>
                                        <span class="badge {{ $fl->is_active ? 'badge-success' : 'badge-danger' }} md:hidden">
                                            {{ $fl->is_active ? 'Aktif' : 'Nonaktif' }}
                                        </span>
                                    </div>
                                </td>
                                <td data-label="Nomor Handphone">
                                    <div class="flex items-center gap-1.5 font-mono font-bold text-slate-900">
                                        <x-icon name="phone" class="w-3.5 h-3.5 text-orange-500 flex-none" />
                                        <span class="select-all">{{ $fl->phone ?: '-' }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-500">Gunakan nomor ini untuk masuk</div>
                                </td>
                                <td data-label="NIK Karyawan" class="font-mono text-xs font-bold text-orange-600">
                                    {{ $fl->nik ?: '-' }}
                                </td>
                                <td data-label="Armada Kendaraan" class="text-xs">
                                    <span class="font-semibold text-slate-800">{{ $fl->vehicleName() }}</span>
                                </td>
                                <td data-label="Tugas (30 Hari)" class="font-bold text-slate-800">
                                    {{ $fl->tasks_30d }} penugasan
                                </td>
                                <td data-label="Skor KPI (30H)">
                                    @if($fl->kpi_30d !== null)
                                        <span class="badge {{ \App\Models\PerformanceLog::badgeFor($grade) }}">
                                            {{ number_format($kpi, 2) }} (Grade {{ $grade }})
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-500">Belum dinilai</span>
                                    @endif
                                </td>
                                <td data-label="Status Bertugas">
                                    <span class="badge {{ $fl->is_active ? 'badge-success' : 'badge-danger' }}">
                                        {{ $fl->is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                @if(auth()->user()->canManage())
                                    <td data-card-actions="1" data-label="Aksi Manajemen" class="text-right">
                                        <div class="flex justify-end gap-1.5 flex-wrap">
                                            <a href="{{ route('hrd.freelancers.show', $fl) }}" class="btn btn-ghost btn-sm">Detail & KPI</a>
                                            <a href="{{ route('hrd.freelancers.edit', $fl) }}" class="btn btn-ghost btn-sm">Edit</a>
                                            @if(auth()->user()->isSuperadmin())
                                                <form method="POST" action="{{ route('admin.users.reset', $fl) }}"
                                                      data-confirm="Reset password {{ $fl->name }} ke default?">
                                                    @csrf
                                                    <button type="submit" class="btn btn-primary btn-sm">Reset Pass</button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('hrd.freelancers.request-reset', $fl) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-ghost btn-sm" title="Minta SuperAdmin mereset password">Minta Reset</button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-slate-400 py-6 text-xs">
                                    Tidak ada karyawan yang terdaftar pada divisi ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
</div>
@endsection
