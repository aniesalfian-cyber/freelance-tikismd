@extends('layouts.app', ['title' => 'Manajemen Penugasan & Monitoring Status', 'subtitle' => 'Distribusi tugas pasti harian dan tugas dinamis pada Gerai & Corporate'])

@section('content')
<!-- Summary Status Pills -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 rise">
    <div class="glass p-4 flex items-center justify-between">
        <span class="text-xs font-bold text-slate-600">Belum Diproses</span>
        <span class="text-xl font-extrabold text-slate-900">{{ (int) ($counts['pending'] ?? 0) }}</span>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <span class="text-xs font-bold text-orange-700">Sedang Diproses</span>
        <span class="text-xl font-extrabold text-orange-600">{{ (int) ($counts['in_progress'] ?? 0) }}</span>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <span class="text-xs font-bold text-emerald-700">Selesai Diproses</span>
        <span class="text-xl font-extrabold text-emerald-600">{{ (int) ($counts['completed'] ?? 0) }}</span>
    </div>
    <div class="glass p-4 flex items-center justify-between">
        <span class="text-xs font-bold text-red-700">Dibatalkan</span>
        <span class="text-xl font-extrabold text-red-600">{{ (int) ($counts['cancelled'] ?? 0) }}</span>
    </div>
</div>

<!-- Filter & Actions -->
<div class="glass p-4 sm:p-5 space-y-3.5">
    <form method="GET" action="{{ route('hrd.tasks.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5 items-end">
        <div>
            <label for="f-date" class="label">Tanggal Tugas</label>
            <input id="f-date" type="date" name="date" value="{{ $date->toDateString() }}" class="input min-h-11">
        </div>

        <div>
            <label for="f-q" class="label">Cari Judul / No. Tugas</label>
            <input id="f-q" type="text" name="q" value="{{ request('q') }}" placeholder="Ketik kata kunci..." class="input min-h-11">
        </div>

        <div>
            <label for="f-division" class="label">Divisi Kurir</label>
            <select id="f-division" name="division" class="input min-h-11">
                <option value="">Semua Divisi</option>
                @foreach($divisions as $dKey => $dData)
                    <option value="{{ $dKey }}" @selected(request('division') === $dKey)>{{ $dData['name'] }} ({{ $dData['vehicle_short'] }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="f-user" class="label">Karyawan</label>
            <select id="f-user" name="user_id" class="input min-h-11">
                <option value="">Semua Karyawan</option>
                @foreach($freelancers as $fl)
                    <option value="{{ $fl->id }}" @selected(request('user_id') == $fl->id)>{{ $fl->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="f-outlet" class="label">Lokasi (Gerai / Corp)</label>
            <select id="f-outlet" name="outlet_id" class="input min-h-11">
                <option value="">Semua Gerai & Corporate</option>
                @if($outlets->where('is_main_office', false)->where('type', 'corporate')->isNotEmpty())
                    <optgroup label="Corporate Rekan">
                        @foreach($outlets->where('is_main_office', false)->where('type', 'corporate') as $o)
                            <option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($outlets->where('is_main_office', false)->where('type', '!=', 'corporate')->isNotEmpty())
                    <optgroup label="Gerai Cabang">
                        @foreach($outlets->where('is_main_office', false)->where('type', '!=', 'corporate') as $o)
                            <option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
                @if($outlets->where('is_main_office', true)->isNotEmpty())
                    <optgroup label="Kantor Utama">
                        @foreach($outlets->where('is_main_office', true) as $o)
                            <option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>
                        @endforeach
                    </optgroup>
                @endif
            </select>
        </div>

        <div>
            <label for="f-status" class="label">Status Pengerjaan</label>
            <select id="f-status" name="status" class="input min-h-11">
                <option value="">Semua Status</option>
                @foreach(\App\Models\TaskAssignment::STATUS_LABELS as $sk => $sl)
                    <option value="{{ $sk }}" @selected(request('status') === $sk)>{{ $sl }}</option>
                @endforeach
            </select>
        </div>

        <div class="sm:col-span-2 lg:col-span-6 flex flex-wrap items-center justify-between gap-3 pt-1 border-t border-slate-200/80">
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-2 text-xs font-semibold text-slate-700 cursor-pointer min-h-11">
                    <input type="checkbox" name="range" value="all" @checked($range) class="check"> Tampilkan Semua Tanggal
                </label>
                <button type="submit" class="btn btn-primary btn-sm min-h-11 px-4">Terapkan Filter</button>
                @if(request()->hasAny(['q', 'division', 'user_id', 'outlet_id', 'status', 'range']))
                    <a href="{{ route('hrd.tasks.index', ['date' => $date->toDateString()]) }}" class="btn btn-ghost btn-sm min-h-11">Reset</a>
                @endif
            </div>

            @if(auth()->user()->canManage())
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('hrd.tasks.template') }}" class="btn btn-ghost btn-sm min-h-11" title="Unduh Format Excel Penugasan Kurir">
                        <x-icon name="download" class="w-4 h-4" /> Format Excel
                    </a>
                    <a href="{{ route('hrd.tasks.import.show') }}" class="btn btn-ghost btn-sm min-h-11" title="Import Penugasan Massal dari File Excel">
                        <x-icon name="upload" class="w-4 h-4" /> Import Excel
                    </a>
                    <button type="submit" form="generate-daily-tasks-form" class="btn btn-ghost btn-sm min-h-11">
                        <x-icon name="repeat" class="w-4 h-4" /> Generate Tugas Pasti
                    </button>
                    <a href="{{ route('hrd.tasks.create') }}" class="btn btn-primary btn-sm min-h-11">
                        <x-icon name="plus" class="w-4 h-4" /> Beri Tugas Baru
                    </a>
                </div>
            @endif
        </div>
    </form>

    @if(auth()->user()->canManage())
        <form id="generate-daily-tasks-form" method="POST" action="{{ route('hrd.tasks.generate') }}" class="hidden">
            @csrf
            <input type="hidden" name="date" value="{{ max(today()->toDateString(), $date->toDateString()) }}">
        </form>
    @endif
</div>

<!-- Tasks Card Grid -->
@if($tasks->isEmpty())
    <div class="glass p-10 text-center space-y-2 rise">
        <div class="text-sm font-extrabold text-slate-900">Tidak ada penugasan pada filter ini</div>
        <p class="text-xs text-slate-500">Ubah parameter filter tanggal, karyawan, atau lokasi untuk melihat penugasan lainnya.</p>
    </div>
@else
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 rise">
        @foreach($tasks as $t)
            @php
                $outlet = $t->outlet;
                $isMain = (bool) $outlet?->is_main_office;
                $isCorp = ! $isMain && (bool) $outlet?->isCorporate();
            @endphp
            <article class="glass glass-hover p-4 flex flex-col justify-between gap-3.5">
                <div class="space-y-3">
                    <!-- Card Header: Number + Date & Execution Status -->
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-mono font-extrabold text-orange-700 text-xs truncate">{{ $t->task_number }}</div>
                            <div class="text-[11px] text-slate-500 font-medium">{{ $t->assigned_date->translatedFormat('d M Y') }}</div>
                        </div>
                        <span class="badge {{ $t->statusBadge() }} flex-none">{{ $t->statusLabel() }}</span>
                    </div>

                    <!-- Task Title & Category -->
                    <div>
                        <a href="{{ route('hrd.tasks.show', $t) }}" class="font-extrabold text-slate-900 hover:text-orange-700 transition text-sm leading-snug line-clamp-2">
                            {{ $t->title }}
                        </a>
                        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                            <span class="badge {{ $t->task_type === 'pasti_harian' ? 'badge-info' : 'badge-orange' }} text-[10px]">
                                {{ $t->typeLabel() }}
                            </span>
                            @if($t->is_location_valid === true)
                                <span class="badge badge-success text-[10px]">Valid Radius ({{ $t->start_distance_meters ?? 0 }}m)</span>
                            @elseif($t->is_location_valid === false)
                                <span class="badge badge-danger text-[10px]">Di Luar Radius ({{ $t->start_distance_meters ?? '-' }}m)</span>
                            @endif
                        </div>
                    </div>

                    <!-- Destination Location (Gerai / Corporate) -->
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200/90 space-y-1 text-xs">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">Lokasi Tujuan</span>
                            @if($isMain)
                                <span class="badge badge-orange text-[10px] py-0.5 px-2">Kantor Utama</span>
                            @elseif($isCorp)
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-extrabold bg-purple-100 text-purple-900 border border-purple-300">Corporate</span>
                            @else
                                <span class="badge badge-info text-[10px] py-0.5 px-2">Gerai</span>
                            @endif
                        </div>
                        <div class="font-extrabold text-slate-900 truncate">{{ $outlet?->name ?? '-' }}</div>
                        @if($outlet?->code)
                            <div class="font-mono text-[11px] text-slate-500 truncate">{{ $outlet->code }}</div>
                        @endif
                    </div>

                    <!-- Courier & SLA Schedule -->
                    <div class="grid grid-cols-2 gap-2.5 text-xs pt-0.5">
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold uppercase text-slate-500">Kurir Pelaksana</div>
                            <div class="font-bold text-slate-900 truncate mt-0.5">{{ $t->user?->name ?? '-' }}</div>
                            @if($t->user)
                                <div class="text-[11px] text-slate-600 truncate">{{ $t->user->vehicleShort() }} &bull; {{ $t->user->specialization }}</div>
                            @endif
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-bold uppercase text-slate-500">Jadwal & Durasi</div>
                            <div class="font-mono font-bold text-slate-900 mt-0.5">Mulai {{ $t->startTimeShort() }}</div>
                            <div class="text-[11px] text-slate-600">
                                {{ $t->actual_duration_minutes !== null ? $t->actual_duration_minutes.' mnt' : '-' }} / SLA {{ $t->estimated_duration_minutes }} mnt
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card Footer: GPS State & Icon-Only Actions -->
                <div class="pt-2.5 border-t border-slate-200/80 flex items-center justify-between gap-2">
                    <span class="text-[11px] text-slate-500 font-medium truncate">
                        @if($t->is_location_valid === null)
                            Menunggu eksekusi lapangan
                        @elseif($t->completed_at)
                            Selesai {{ $t->completed_at->format('H:i') }} WITA
                        @else
                            Telemetri terekam
                        @endif
                    </span>

                    <div class="flex items-center gap-2 flex-none">
                        @if(auth()->user()->canManage() && $t->status === 'pending')
                            <a href="{{ route('hrd.tasks.edit', $t) }}"
                               class="btn btn-ghost w-11 h-11 p-0 rounded-xl"
                               title="Edit Tugas {{ $t->task_number }}"
                               aria-label="Edit tugas {{ $t->task_number }}">
                                <x-icon name="edit" class="w-4 h-4 text-orange-600" aria-hidden="true" />
                            </a>
                        @endif
                        <a href="{{ route('hrd.tasks.show', $t) }}"
                           class="btn btn-ghost w-11 h-11 p-0 rounded-xl"
                           title="Detail & Peta Tugas {{ $t->task_number }}"
                           aria-label="Detail tugas {{ $t->task_number }}">
                            <x-icon name="eye" class="w-4 h-4 text-slate-800" aria-hidden="true" />
                        </a>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <div class="mt-4">{{ $tasks->links() }}</div>
@endif
@endsection
