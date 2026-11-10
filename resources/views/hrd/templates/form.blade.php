@extends('layouts.app', ['title' => $template->exists ? 'Edit Template Tugas' : 'Buat Template Tugas Harian'])

@section('content')
<div class="glass p-6 max-w-2xl mx-auto">
    <div class="orn-line mb-2"></div>
    <h2 class="text-lg font-extrabold text-white mb-4">{{ $template->exists ? 'Perbarui Template Tugas' : 'Tambah Template Tugas Pasti Harian' }}</h2>

    <form method="POST" action="{{ $template->exists ? route('hrd.templates.update', $template) : route('hrd.templates.store') }}" class="space-y-4">
        @csrf
        @if($template->exists) @method('PUT') @endif

        <div>
            <label class="label">Judul Tugas Rutin</label>
            <input type="text" name="title" value="{{ old('title', $template->title) }}" placeholder="Contoh: Pengecekan Stok Awal Gerai" required class="input">
        </div>

        <div>
            <label class="label">Deskripsi & Instruksi Standar</label>
            <textarea name="description" rows="2" class="input">{{ old('description', $template->description) }}</textarea>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Lokasi Gerai Pengerjaan</label>
                <select name="default_outlet_id" required class="input">
                    <option value="">-- Pilih Gerai --</option>
                    @foreach($outlets as $o)
                        <option value="{{ $o->id }}" @selected(old('default_outlet_id', $template->default_outlet_id) == $o->id)>{{ $o->name }} ({{ $o->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Ditugaskan Kepada</label>
                <select name="default_user_id" class="input">
                    <option value="">Semua Karyawan Aktif</option>
                    @foreach($freelancers as $fl)
                        <option value="{{ $fl->id }}" @selected(old('default_user_id', $template->default_user_id) == $fl->id)>{{ $fl->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Jam Target Mulai</label>
                <input type="time" name="scheduled_start_time" value="{{ old('scheduled_start_time', $template->startTimeShort()) }}" required class="input">
            </div>
            <div>
                <label class="label">Target Durasi / SLA (Menit)</label>
                <input type="number" min="5" max="720" name="estimated_duration_minutes" value="{{ old('estimated_duration_minutes', $template->estimated_duration_minutes) }}" required class="input">
            </div>
        </div>

        <div>
            <label class="label">Hari Berlaku (Kosongkan jika berlaku setiap hari)</label>
            @php $selectedDays = old('active_days', $template->active_days ?? []); @endphp
            <div class="flex flex-wrap gap-3 pt-1">
                @foreach(\App\Models\TaskTemplate::DAY_LABELS as $iso => $dLabel)
                    <label class="inline-flex items-center gap-1.5 text-xs text-white cursor-pointer">
                        <input type="checkbox" name="active_days[]" value="{{ $iso }}" @checked(in_array($iso, (array) $selectedDays)) class="check">
                        <span>{{ $dLabel }}</span>
                    </label>
                @endforeach
            </div>
        </div>

        <label class="inline-flex items-center gap-2 cursor-pointer pt-1">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template->is_active ?? true)) class="check">
            <span class="text-sm font-semibold text-white">Template Aktif</span>
        </label>

        <div class="flex items-center justify-end gap-2 pt-3">
            <a href="{{ route('hrd.templates.index') }}" class="btn btn-ghost">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Template</button>
        </div>
    </form>
</div>
@endsection
