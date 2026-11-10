@extends('layouts.app', ['title' => $freelancer->exists ? 'Edit Data Karyawan' : 'Daftarkan Karyawan Baru'])

@section('content')
<div class="glass p-6 max-w-2xl mx-auto">
    <div class="orn-line mb-2"></div>
    <h2 class="text-lg font-extrabold text-white mb-4">{{ $freelancer->exists ? 'Perbarui Profil '.$freelancer->name : 'Registrasi Karyawan' }}</h2>

    <form method="POST" action="{{ $freelancer->exists ? route('hrd.freelancers.update', $freelancer) : route('hrd.freelancers.store') }}" class="space-y-4">
        @csrf
        @if($freelancer->exists) @method('PUT') @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="label">NIK Karyawan</label>
                <input type="text" name="nik" value="{{ old('nik', $freelancer->nik) }}" placeholder="FL-0006" required class="input font-mono">
            </div>
            <div class="sm:col-span-2">
                <label class="label">Nama Lengkap</label>
                <input type="text" name="name" value="{{ old('name', $freelancer->name) }}" required class="input">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Email Login</label>
                <input type="email" name="email" value="{{ old('email', $freelancer->email) }}" required class="input">
            </div>
            <div>
                <label class="label">Nomor WhatsApp Aktif</label>
                <input type="text" name="phone" value="{{ old('phone', $freelancer->phone) }}" placeholder="0812xxxxxxxx" required class="input">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="label">Bidang / Spesialisasi</label>
                <input type="text" name="specialization" value="{{ old('specialization', $freelancer->specialization) }}" placeholder="Operasional Gerai / Audit Stok" class="input">
            </div>
            <div>
                <label class="label">Tarif Satuan Jam (Rp)</label>
                <input type="number" step="500" min="0" name="hourly_rate" value="{{ old('hourly_rate', $freelancer->hourly_rate ?? 25000) }}" class="input">
            </div>
        </div>

        <label class="inline-flex items-center gap-2 cursor-pointer pt-1">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $freelancer->is_active ?? true)) class="check">
            <span class="text-sm font-semibold text-white">Status Aktif Bertugas</span>
        </label>

        @if(!$freelancer->exists)
            <p class="text-xs text-slate-400">
                Akun login akan otomatis dibuat dengan password acak dan ditampilkan dalam modal siap kirim ke WhatsApp.
            </p>
        @endif

        <div class="flex items-center justify-end gap-2 pt-3">
            <a href="{{ route('hrd.freelancers.index') }}" class="btn btn-ghost">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan Karyawan</button>
        </div>
    </form>
</div>
@endsection
