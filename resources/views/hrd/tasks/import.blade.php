@extends('layouts.app', ['title' => 'Import Penugasan Kurir dari Excel', 'subtitle' => 'Unggah berkas Excel penugasan massal untuk efisiensi distribusi tugas'])

@section('content')
<div class="max-w-3xl mx-auto space-y-6 rise">
    <!-- Header Back Navigation -->
    <div class="flex items-center justify-between gap-3">
        <div>
            <div class="orn-line mb-2"></div>
            <h2 class="text-xl font-black text-slate-900 tracking-tight">Import Data Penugasan Kurir</h2>
            <p class="text-xs text-slate-600 mt-0.5">Distribusikan tugas harian secara massal menggunakan file Excel (.xlsx / .csv).</p>
        </div>
        <a href="{{ route('hrd.tasks.index') }}" class="btn btn-ghost btn-sm shadow-xs">
            <x-icon name="arrow-left" class="w-4 h-4" /> Kembali ke Daftar Tugas
        </a>
    </div>

    <!-- Error Summary if Any -->
    @if(session('import_errors') && count(session('import_errors')) > 0)
        <div class="glass p-5 border-l-4 border-l-amber-500 bg-amber-50/60 border border-amber-200 text-xs space-y-2 shadow-xs">
            <div class="flex items-center gap-2 font-bold text-sm text-amber-900">
                <x-icon name="alert" class="w-4 h-4 text-amber-600 flex-none" />
                <span>Beberapa Baris Data Memerlukan Perhatian:</span>
            </div>
            <p class="text-amber-800">Tugas yang valid tetap berhasil disimpan. Baris berikut dilewati karena data tidak lengkap:</p>
            <ul class="list-disc list-inside space-y-1 text-amber-900 max-h-40 overflow-y-auto pl-1">
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Step 1: Download Official Template -->
    <div class="paper-card p-6 space-y-3">
        <div class="flex items-start justify-between gap-4 flex-wrap sm:flex-nowrap">
            <div class="space-y-1">
                <span class="badge badge-orange font-bold">Langkah 1</span>
                <h3 class="text-base font-extrabold text-slate-900">Unduh Format Resmi Excel</h3>
                <p class="text-xs text-slate-600 leading-relaxed">
                    Gunakan template resmi yang telah dilengkapi dengan contoh pengisian serta <b>Sheet Referensi</b> berisi daftar 17 Nomor Handphone Kurir, Divisi Operasional (R2 / R4), dan Kode Gerai Samarinda.
                </p>
            </div>
            <a href="{{ route('hrd.tasks.template') }}" class="btn btn-primary flex-none py-2.5 px-4 shadow-sm">
                <x-icon name="download" class="w-4 h-4" /> Unduh Format Excel (.xlsx)
            </a>
        </div>
    </div>

    <!-- Step 2: Upload Filled File -->
    <div class="paper-card p-6 space-y-4">
        <div class="space-y-1">
            <span class="badge badge-info font-bold">Langkah 2</span>
            <h3 class="text-base font-extrabold text-slate-900">Unggah Berkas Excel yang Telah Diisi</h3>
            <p class="text-xs text-slate-600">Pastikan data tugas diisi pada Sheet 1 ("Formulir Tugas") mulai baris ke-5.</p>
        </div>

        <form method="POST" action="{{ route('hrd.tasks.import') }}" enctype="multipart/form-data" class="space-y-5"
              x-data="{ fileName: '', fileSize: '', isDragging: false }">
            @csrf

            <!-- Drag & Drop File Container -->
            <div class="border-2 border-dashed rounded-2xl p-6 text-center transition-all duration-200 cursor-pointer relative"
                 :class="isDragging ? 'border-orange-500 bg-orange-50/50' : 'border-slate-300 hover:border-slate-400 bg-slate-50/50'"
                 @dragover.prevent="isDragging = true"
                 @dragleave.prevent="isDragging = false"
                 @drop.prevent="isDragging = false; $refs.fileInput.files = $event.dataTransfer.files; fileName = $refs.fileInput.files[0]?.name; fileSize = ($refs.fileInput.files[0]?.size / 1024).toFixed(1) + ' KB'">

                <input type="file" name="excel_file" x-ref="fileInput" accept=".xlsx,.xls,.csv" required
                       @change="fileName = $refs.fileInput.files[0]?.name; fileSize = ($refs.fileInput.files[0]?.size / 1024).toFixed(1) + ' KB'"
                       class="absolute inset-0 opacity-0 cursor-pointer w-full h-full">

                <div class="space-y-2 pointer-events-none">
                    <div class="w-12 h-12 rounded-2xl bg-orange-100 text-orange-600 flex items-center justify-center mx-auto shadow-xs">
                        <x-icon name="upload" class="w-6 h-6" />
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-800">
                            <span x-show="!fileName">Klik untuk memilih file atau seret file Excel ke sini</span>
                            <span x-show="fileName" x-text="fileName" class="text-orange-600 font-extrabold"></span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-0.5">
                            <span x-show="!fileName">Format didukung: <b>.xlsx, .xls, .csv</b> (Maksimal 10 MB)</span>
                            <span x-show="fileName" x-text="'Ukuran file: ' + fileSize" class="font-mono text-slate-600"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-2.5 pt-2">
                <a href="{{ route('hrd.tasks.index') }}" class="btn btn-ghost">Batal</a>
                <button type="submit" class="btn btn-primary px-6 shadow-md">
                    <x-icon name="check" class="w-4 h-4" /> Proses & Impor Penugasan
                </button>
            </div>
        </form>
    </div>

    <!-- Quick Reference Guidelines -->
    <div class="glass p-5 space-y-3 text-xs text-slate-600">
        <h4 class="font-extrabold text-slate-900 text-sm">💡 Panduan Cepat Format Kolom Excel:</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-[11px] leading-relaxed">
            <div class="p-3 rounded-xl bg-white border border-slate-200 space-y-1">
                <div class="font-bold text-slate-800">1. Nomor HP Kurir (Kolom B)</div>
                <p>Kunci pencocokan utama kurir. Masukkan no HP kurir terdaftar (misal: <code>081349497879</code> atau <code>6281349497879</code>).</p>
            </div>
            <div class="p-3 rounded-xl bg-white border border-slate-200 space-y-1">
                <div class="font-bold text-slate-800">2. Kode Gerai (Kolom E)</div>
                <p>Lokasi titik awal/pos tugas. Contoh: <code>PST-001</code> (Kantor Utama Samarinda), <code>GR-001</code> (Cabang Ilir).</p>
            </div>
            <div class="p-3 rounded-xl bg-white border border-slate-200 space-y-1">
                <div class="font-bold text-slate-800">3. Tanggal & Jam Mulai (Kolom I & J)</div>
                <p>Format Tanggal: <code>YYYY-MM-DD</code> (contoh: <code>{{ today()->toDateString() }}</code>) & Jam Mulai: <code>HH:MM</code> (contoh: <code>09:00</code>).</p>
            </div>
            <div class="p-3 rounded-xl bg-white border border-slate-200 space-y-1">
                <div class="font-bold text-slate-800">4. Tipe Tugas & Durasi SLA (Kolom H & K)</div>
                <p>Isi <code>pasti_harian</code> atau <code>tidak_pasti</code>. Durasi SLA dalam satuan menit (contoh: <code>120</code> atau <code>180</code>).</p>
            </div>
        </div>
    </div>
</div>
@endsection
