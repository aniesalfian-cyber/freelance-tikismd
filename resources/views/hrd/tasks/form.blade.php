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
                    @foreach($freelancers as $fl)
                        <option value="{{ $fl->id }}" @selected(old('user_id', $task->user_id) == $fl->id)>{{ $fl->name }} ({{ $fl->nik }})</option>
                    @endforeach
                </select>
            </div>
        @else
            <div>
                <label class="label">Pilih Karyawan Penerima Tugas (Dapat pilih lebih dari satu)</label>
                @php $oldUsers = (array) old('user_ids', []); @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 p-3 rounded-2xl bg-navy-950/60 border border-white/10 max-h-56 overflow-y-auto">
                    @foreach($freelancers as $fl)
                        <label class="flex items-center gap-2.5 p-2 rounded-xl hover:bg-white/5 cursor-pointer text-xs">
                            <input type="checkbox" name="user_ids[]" value="{{ $fl->id }}" @checked(in_array($fl->id, $oldUsers)) class="check">
                            <div>
                                <div class="font-bold text-white">{{ $fl->name }}</div>
                                <div class="text-[11px] text-slate-400">{{ $fl->nik }} &bull; {{ $fl->specialization ?: 'Operasional' }}</div>
                            </div>
                        </label>
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
