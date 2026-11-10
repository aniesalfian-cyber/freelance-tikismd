@extends('layouts.app', ['title' => 'Konfigurasi Sistem & Parameter KPI', 'subtitle' => 'Pengaturan korporat, jam operasional presensi, geofencing, dan bobot penilaian'])

@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6 max-w-4xl mx-auto">
    @csrf
    @method('PUT')

    <div class="glass p-6 space-y-4">
        <div class="orn-line mb-1"></div>
        <h2 class="text-base font-extrabold text-white">Identitas Korporat (Kop Laporan Resmi)</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Nama Perusahaan</label>
                <input type="text" name="company_name" value="{{ old('company_name', \App\Models\Setting::get('company_name')) }}" required class="input">
            </div>
            <div>
                <label class="label">Kontak Resmi (Telp / Email)</label>
                <input type="text" name="company_contact" value="{{ old('company_contact', \App\Models\Setting::get('company_contact')) }}" required class="input">
            </div>
        </div>
        <div>
            <label class="label">Alamat Kantor Pusat</label>
            <input type="text" name="company_address" value="{{ old('company_address', \App\Models\Setting::get('company_address')) }}" required class="input">
        </div>
    </div>

    <div class="glass p-6 space-y-4">
        <div class="orn-line mb-1"></div>
        <h2 class="text-base font-extrabold text-white">Parameter Presensi & Geofencing GPS</h2>
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div>
                <label class="label">Jam Mulai Kerja</label>
                <input type="time" name="work_start_time" value="{{ old('work_start_time', \App\Models\Setting::get('work_start_time')) }}" required class="input">
            </div>
            <div>
                <label class="label">Jam Pulang Kerja</label>
                <input type="time" name="work_end_time" value="{{ old('work_end_time', \App\Models\Setting::get('work_end_time')) }}" required class="input">
            </div>
            <div>
                <label class="label">Batas Akurasi GPS (m)</label>
                <input type="number" name="max_gps_accuracy" value="{{ old('max_gps_accuracy', \App\Models\Setting::get('max_gps_accuracy')) }}" required class="input">
            </div>
            <div>
                <label class="label">Radius Gerai Default (m)</label>
                <input type="number" name="default_radius" value="{{ old('default_radius', \App\Models\Setting::get('default_radius')) }}" required class="input">
            </div>
        </div>
    </div>

    <div class="glass p-6 space-y-4">
        <div class="orn-line mb-1"></div>
        <h2 class="text-base font-extrabold text-white">Komposisi Bobot Formula KPI (Total Wajib 100%)</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div>
                <label class="label">Penyelesaian Tugas (%)</label>
                <input type="number" name="weight_completion" value="{{ old('weight_completion', \App\Models\Setting::get('weight_completion')) }}" required class="input">
            </div>
            <div>
                <label class="label">Ketepatan Waktu SLA (%)</label>
                <input type="number" name="weight_time" value="{{ old('weight_time', \App\Models\Setting::get('weight_time')) }}" required class="input">
            </div>
            <div>
                <label class="label">Kepatuhan Lokasi (%)</label>
                <input type="number" name="weight_location" value="{{ old('weight_location', \App\Models\Setting::get('weight_location')) }}" required class="input">
            </div>
            <div>
                <label class="label">Kedisiplinan Presensi (%)</label>
                <input type="number" name="weight_attendance" value="{{ old('weight_attendance', \App\Models\Setting::get('weight_attendance')) }}" required class="input">
            </div>
        </div>
    </div>

    <div class="flex justify-end">
        <button type="submit" class="btn btn-primary px-6">Simpan Pengaturan Sistem</button>
    </div>
</form>
@endsection
