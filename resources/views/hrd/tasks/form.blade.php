@extends('layouts.app', ['title' => $task->exists ? 'Edit Penugasan' : 'Distribusi Tugas Baru', 'subtitle' => 'Atur waktu mulai pengerjaan, target durasi SLA, dan lokasi gerai'])

@section('content')
<div class="glass p-6 max-w-2xl mx-auto">
    <div class="orn-line mb-2"></div>
    <h2 class="text-lg font-extrabold text-white mb-4">{{ $task->exists ? 'Perbarui Tugas '.$task->task_number : 'Formulir Penugasan Karyawan' }}</h2>

    <form method="POST" action="{{ $task->exists ? route('hrd.tasks.update', $task) : route('hrd.tasks.store') }}" class="space-y-4">
        @csrf
        @if($task->exists) @method('PUT') @endif

        <div>
            <label class="label">Judul Tugas</label>
            <input type="text" name="title" value="{{ old('title', $task->title) }}" placeholder="Contoh: Audit Stok Mendadak Gerai Samarinda Seberang" required class="input">
        </div>

        <div>
            <label class="label">Instruksi / Detail Pengerjaan</label>
            <textarea name="description" rows="2" class="input">{{ old('description', $task->description) }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Kategori Tugas</label>
                <select name="task_type" required class="input">
                    @foreach(\App\Models\TaskAssignment::TYPE_LABELS as $tk => $tl)
                        <option value="{{ $tk }}" @selected(old('task_type', $task->task_type) === $tk)>{{ $tl }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Titik Lokasi Gerai Pengerjaan</label>
                <select name="outlet_id" required class="input">
                    <option value="">-- Pilih Gerai --</option>
                    @foreach($outlets as $o)
                        <option value="{{ $o->id }}" @selected(old('outlet_id', $task->outlet_id) == $o->id)>{{ $o->name }} ({{ $o->code }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="label">Tanggal Penugasan</label>
                <input type="date" name="assigned_date" value="{{ old('assigned_date', ($task->assigned_date ?? today())->format('Y-m-d')) }}" required class="input">
            </div>
            <div>
                <label class="label">Waktu Mulai Diproses</label>
                <input type="time" name="scheduled_start_time" value="{{ old('scheduled_start_time', $task->startTimeShort()) }}" required class="input">
            </div>
            <div>
                <label class="label">Estimasi Durasi / SLA (Menit)</label>
                <input type="number" min="5" max="720" name="estimated_duration_minutes" value="{{ old('estimated_duration_minutes', $task->estimated_duration_minutes ?? 30) }}" required class="input">
            </div>
        </div>

        @if($task->exists)
            <div>
                <label class="label">Karyawan Penerima Tugas</label>
                <select name="user_id" required class="input">
                    @foreach($freelancers->groupBy('specialization') as $grpName => $grpUsers)
                        <optgroup label="{{ $grpName }} ({{ $grpUsers->first()?->vehicleShort() }})">
                            @foreach($grpUsers as $fl)
                                <option value="{{ $fl->id }}" @selected(old('user_id', $task->user_id) == $fl->id)>{{ $fl->name }} ({{ $fl->phone }})</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        @else
            <div x-data="{
                selectDivision(divName) {
                    let boxes = document.querySelectorAll(`input[data-division='${divName}']`);
                    let allChecked = Array.from(boxes).every(b => b.checked);
                    boxes.forEach(b => b.checked = !allChecked);
                }
            }">
                <div class="flex items-center justify-between mb-1.5 flex-wrap gap-2">
                    <label class="label mb-0">Pilih Karyawan Penerima Tugas (Berdasarkan Divisi)</label>
                    <div class="text-[11px] text-slate-500 font-medium">Klik tombol divisi untuk memilih tim sekaligus</div>
                </div>

                <!-- Quick Selection per Division -->
                <div class="flex flex-wrap gap-1.5 mb-2.5">
                    @foreach($freelancers->groupBy('specialization') as $dName => $dUsers)
                        <button type="button" @click="selectDivision('{{ $dName }}')"
                                class="btn btn-ghost btn-sm text-[11px] py-1 px-2.5 shadow-xs">
                            + {{ $dName }} ({{ $dUsers->count() }})
                        </button>
                    @endforeach
                </div>

                @php $oldUsers = (array) old('user_ids', []); @endphp
                <div class="space-y-3 max-h-72 overflow-y-auto p-3 rounded-2xl bg-slate-50 border border-slate-200">
                    @foreach($freelancers->groupBy('specialization') as $dName => $dUsers)
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between px-1">
                                <span class="badge {{ $dUsers->first()?->divisionBadge() }} text-[10px] uppercase font-bold">{{ $dName }}</span>
                                <span class="text-[11px] text-slate-500 font-semibold">{{ $dUsers->first()?->vehicleName() }} &bull; {{ $dUsers->first()?->operationalScopeShort() }}</span>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                @foreach($dUsers as $fl)
                                    <label class="flex items-center gap-2.5 p-2 rounded-xl bg-white border border-slate-200 hover:border-orange-300 cursor-pointer text-xs shadow-xs transition">
                                        <input type="checkbox" name="user_ids[]" value="{{ $fl->id }}" data-division="{{ $dName }}" @checked(in_array($fl->id, $oldUsers)) class="check">
                                        <div class="min-w-0">
                                            <div class="font-bold text-slate-900 truncate">{{ $fl->name }}</div>
                                            <div class="text-[11px] text-slate-500 font-mono">{{ $fl->phone }}</div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex items-center justify-end gap-2 pt-3">
            <a href="{{ route('hrd.tasks.index') }}" class="btn btn-ghost">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan & Distribusikan Tugas</button>
        </div>
    </form>
</div>
@endsection
