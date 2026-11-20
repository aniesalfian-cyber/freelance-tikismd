@extends('layouts.app', ['title' => 'Manajemen Penugasan & Monitoring Status', 'subtitle' => 'Distribusi tugas pasti harian dan tugas dinamis (tidak pasti)'])

@section('content')
<!-- Summary Status Pills -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 rise">
    <div class="glass p-4 flex items-center justify-between">
        <span class="text-xs font-bold text-slate-400">Belum Diproses</span>
        <span class="text-xl font-extrabold text-white">{{ (int) ($counts['pending'] ?? 0) }}</span>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <span class="text-xs font-bold text-orange-400">Sedang Diproses</span>
        <span class="text-xl font-extrabold text-orange-400">{{ (int) ($counts['in_progress'] ?? 0) }}</span>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <span class="text-xs font-bold text-emerald-400">Selesai Diproses</span>
        <span class="text-xl font-extrabold text-emerald-400">{{ (int) ($counts['completed'] ?? 0) }}</span>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <span class="text-xs font-bold text-red-400">Dibatalkan</span>
        <span class="text-xl font-extrabold text-red-400">{{ (int) ($counts['cancelled'] ?? 0) }}</span>
    </div>
</div>

<!-- Filter & Actions -->
<div class="glass p-5 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <form method="GET" action="{{ route('hrd.tasks.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
            <input type="date" name="date" value="{{ $date->toDateString() }}" class="input sm:w-40">
            <label class="inline-flex items-center gap-1.5 text-xs text-slate-300 px-2">
                <input type="checkbox" name="range" value="all" @checked($range) class="check"> Semua Tanggal
            </label>
            <select name="division" class="input sm:w-56">
                <option value="">Semua Divisi</option>
                @foreach($divisions as $dKey => $dData)
                    <option value="{{ $dKey }}" @selected(request('division') === $dKey)>{{ $dData['name'] }} ({{ $dData['vehicle_short'] }})</option>
                @endforeach
            </select>
            <select name="user_id" class="input sm:w-44">
                <option value="">Semua Karyawan</option>
                @foreach($freelancers as $fl)
                    <option value="{{ $fl->id }}" @selected(request('user_id') == $fl->id)>{{ $fl->name }}</option>
                @endforeach
            </select>
            <select name="outlet_id" class="input sm:w-52">
                <option value="">Semua Gerai & Corporate</option>
                @if($outlets->where('is_main_office', false)->where('type', 'corporate')->isNotEmpty())
                    <optgroup label="🏢 Corporate Rekan">
                        @foreach($outlets->where('is_main_office', false)->where('type', 'corporate') as $o)
                            <option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($outlets->where('is_main_office', false)->where('type', '!=', 'corporate')->isNotEmpty())
                    <optgroup label="🏪 Gerai Cabang">
                        @foreach($outlets->where('is_main_office', false)->where('type', '!=', 'corporate') as $o)
                            <option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($outlets->where('is_main_office', true)->isNotEmpty())
                    <optgroup label="⭐ Kantor Utama">
                        @foreach($outlets->where('is_main_office', true) as $o)
                            <option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
            <select name="status" class="input sm:w-40">
                <option value="">Semua Status</option>
                @foreach(\App\Models\TaskAssignment::STATUS_LABELS as $sk => $sl)
                    <option value="{{ $sk }}" @selected(request('status') === $sk)>{{ $sl }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-ghost">Filter</button>
        </form>

        @if(auth()->user()->canManage())
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('hrd.tasks.template') }}" class="btn btn-ghost btn-sm" title="Unduh Format Excel Penugasan Kurir">
                    <x-icon name="download" class="w-4 h-4" /> Format Excel
                </a>
                <a href="{{ route('hrd.tasks.import.show') }}" class="btn btn-ghost btn-sm" title="Import Penugasan Massal dari File Excel">
                    <x-icon name="upload" class="w-4 h-4" /> Import Excel
                </a>
                <form method="POST" action="{{ route('hrd.tasks.generate') }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ max(today()->toDateString(), $date->toDateString()) }}">
                    <button type="submit" class="btn btn-ghost btn-sm">
                        <x-icon name="repeat" class="w-4 h-4" /> Generate Tugas Pasti
                    </button>
                </form>
                <a href="{{ route('hrd.tasks.create') }}" class="btn btn-primary btn-sm">
                    <x-icon name="plus" class="w-4 h-4" /> Beri Tugas Baru
                </a>
            </div>
        @endif
    </div>
</div>

<!-- Tasks Table -->
<div class="table-surface">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>No. Tugas & Tanggal</th>
                    <th>Judul Tugas & Tipe</th>
                    <th>Karyawan</th>
                    <th>Lokasi (Gerai / Corp)</th>
                    <th>Jadwal & Durasi</th>
                    <th>Lokasi GPS</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $t)
                    <tr>
                        <td data-card-header="1" data-label="Nomor & Tanggal Penugasan" class="text-xs">
                            <div class="flex items-center justify-between w-full gap-2">
                                <div>
                                    <div class="font-mono font-extrabold text-orange-300 text-xs">{{ $t->task_number }}</div>
                                    <div class="text-slate-400">{{ $t->assigned_date->format('d/m/Y') }}</div>
                                </div>
                                <span class="badge {{ $t->statusBadge() }} md:hidden">{{ $t->statusLabel() }}</span>
                            </div>
                        </td>
                        <td data-label="Judul & Kategori Tugas">
                            <div>
                                <div class="font-extrabold text-white text-sm">{{ $t->title }}</div>
                                <span class="badge {{ $t->task_type === 'pasti_harian' ? 'badge-info' : 'badge-orange' }} mt-1">
                                    {{ $t->typeLabel() }}
                                </span>
                            </div>
                        </td>
                        <td data-label="Karyawan Pelaksana">
                            <div>
                                <div class="font-bold text-slate-900">{{ $t->user?->name }}</div>
                                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                    <span class="badge {{ $t->user?->divisionBadge() }} text-[9px]">{{ $t->user?->specialization }}</span>
                                    <span class="text-[10px] text-slate-500 font-bold">({{ $t->user?->vehicleShort() }})</span>
                                </div>
                            </div>
                        </td>
                        <td data-label="Lokasi Tujuan" class="text-xs text-slate-300">
                            <div>
                                <div class="font-bold text-white flex items-center gap-1.5">
                                    <span>{{ $t->outlet?->name }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-[10px] font-mono text-slate-400">{{ $t->outlet?->code }}</span>
                                    @if($t->outlet?->is_main_office)
                                        <span class="badge badge-orange text-[9px] py-0 px-1">Kantor Utama</span>
                                    @elseif($t->outlet?->isCorporate())
                                        <span class="badge text-[9px] py-0 px-1" style="background: rgba(168, 85, 247, 0.2); color: #c084fc;">Corporate</span>
                                    @else
                                        <span class="badge badge-info text-[9px] py-0 px-1">Gerai</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td data-label="Jadwal & Durasi SLA" class="text-xs">
                            <div>
                                <div>Mulai: <b class="text-white font-mono">{{ $t->startTimeShort() }}</b></div>
                                <div class="text-slate-400">
                                    Durasi: {{ $t->actual_duration_minutes !== null ? $t->actual_duration_minutes.' mnt' : '-' }} / SLA {{ $t->estimated_duration_minutes }} mnt
                                </div>
                            </div>
                        </td>
                        <td data-label="Validasi Radius GPS">
                            @if($t->is_location_valid === true)
                                <span class="badge badge-success">Valid Radius ({{ $t->start_distance_meters ?? 0 }}m)</span>
                            @elseif($t->is_location_valid === false)
                                <span class="badge badge-danger">Di Luar Radius ({{ $t->start_distance_meters ?? '-' }}m)</span>
                            @else
                                <span class="text-xs text-slate-500">Menunggu Eksekusi</span>
                            @endif
                        </td>
                        <td data-label="Status Pengerjaan"><span class="badge {{ $t->statusBadge() }}">{{ $t->statusLabel() }}</span></td>
                        <td data-card-actions="1" data-label="Aksi Penugasan" class="text-right">
                            <div class="flex justify-end gap-1.5">
                                <a href="{{ route('hrd.tasks.show', $t) }}" class="btn btn-ghost btn-sm">Detail & Peta</a>
                                @if(auth()->user()->canManage() && $t->status === 'pending')
                                    <a href="{{ route('hrd.tasks.edit', $t) }}" class="btn btn-ghost btn-sm">Edit</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-400 py-8">Tidak ada penugasan pada filter ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $tasks->links() }}</div>
</div>
@endsection
