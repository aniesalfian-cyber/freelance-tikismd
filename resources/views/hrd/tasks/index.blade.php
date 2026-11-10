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
            <select name="user_id" class="input sm:w-44">
                <option value="">Semua Freelancer</option>
                @foreach($freelancers as $fl)
                    <option value="{{ $fl->id }}" @selected(request('user_id') == $fl->id)>{{ $fl->name }}</option>
                @endforeach
            </select>
            <select name="outlet_id" class="input sm:w-44">
                <option value="">Semua Gerai</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}" @selected(request('outlet_id') == $o->id)>{{ $o->name }}</option>
                @endforeach
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
            <div class="flex items-center gap-2">
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
<div class="glass p-5">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>No. Tugas & Tanggal</th>
                    <th>Judul Tugas & Tipe</th>
                    <th>Freelancer</th>
                    <th>Gerai Lokasi</th>
                    <th>Jadwal & Durasi</th>
                    <th>Lokasi GPS</th>
                    <th>Status</th>
                    <th class="text-right">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($tasks as $t)
                    <tr>
                        <td class="text-xs">
                            <div class="font-mono font-bold text-orange-300">{{ $t->task_number }}</div>
                            <div class="text-slate-400">{{ $t->assigned_date->format('d/m/Y') }}</div>
                        </td>
                        <td>
                            <div class="font-bold text-white">{{ $t->title }}</div>
                            <span class="badge {{ $t->task_type === 'pasti_harian' ? 'badge-info' : 'badge-orange' }} mt-1">
                                {{ $t->typeLabel() }}
                            </span>
                        </td>
                        <td class="font-semibold text-white">{{ $t->user?->name }}</td>
                        <td class="text-xs text-slate-300">{{ $t->outlet?->name }}</td>
                        <td class="text-xs">
                            <div>Mulai: <b class="text-white font-mono">{{ $t->startTimeShort() }}</b></div>
                            <div class="text-slate-400">
                                Durasi: {{ $t->actual_duration_minutes !== null ? $t->actual_duration_minutes.' mnt' : '-' }} / SLA {{ $t->estimated_duration_minutes }} mnt
                            </div>
                        </td>
                        <td>
                            @if($t->is_location_valid === true)
                                <span class="badge badge-success">Valid Radius</span>
                            @elseif($t->is_location_valid === false)
                                <span class="badge badge-danger">Di Luar Radius</span>
                            @else
                                <span class="text-xs text-slate-500">-</span>
                            @endif
                        </td>
                        <td><span class="badge {{ $t->statusBadge() }}">{{ $t->statusLabel() }}</span></td>
                        <td class="text-right">
                            <div class="flex justify-end gap-1.5">
                                <a href="{{ route('hrd.tasks.show', $t) }}" class="btn btn-ghost btn-sm">Detail</a>
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
