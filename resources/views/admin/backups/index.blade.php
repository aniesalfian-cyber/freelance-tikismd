@extends('layouts.app', ['title' => 'Backup & Restore Database MySQL', 'subtitle' => 'Cadangan terkompresi (.sql.gz) dan pemulihan aman tervalidasi'])

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Create & Restore Actions -->
    <div class="space-y-6">
        <div class="glass p-6 space-y-4">
            <div class="orn-line mb-1"></div>
            <h2 class="text-base font-extrabold text-white">One-Click Database Backup</h2>
            <p class="text-xs text-slate-400 leading-relaxed">
                Membuat cadangan penuh seluruh tabel MySQL (<code class="text-orange-300">freelance_hsm</code>) terkompresi <code class="text-orange-300">.sql.gz</code>.
                Penjadwal otomatis juga berjalan setiap pukul 01:00 WIB.
            </p>
            <form method="POST" action="{{ route('admin.backups.store') }}">
                @csrf
                <button type="submit" class="btn btn-primary btn-block">
                    <x-icon name="database" class="w-4 h-4" /> Buat Cadangan Database Baru
                </button>
            </form>
        </div>

        <div class="glass p-6 space-y-4 border-amber-500/30">
            <div class="orn-line mb-1"></div>
            <h2 class="text-base font-extrabold text-white">Safe Restore Wizard</h2>
            <p class="text-xs text-slate-400 leading-relaxed">
                Unggah berkas <code class="text-orange-300">.sql</code> atau <code class="text-orange-300">.sql.gz</code> (maks {{ $maxMb }}MB).
                Sistem akan memvalidasi skema tabel HSM dan otomatis membuat backup pengaman sebelum memulihkan data.
            </p>
            <form method="POST" action="{{ route('admin.backups.restore') }}" enctype="multipart/form-data" class="space-y-3"
                  data-confirm="Database saat ini akan digantikan oleh berkas cadangan. Lanjutkan proses restore?"
                  data-confirm-title="Konfirmasi Restore Database">
                @csrf
                <div>
                    <label class="label">Berkas Backup (.sql / .sql.gz)</label>
                    <input type="file" name="backup_file" accept=".sql,.gz" required class="input text-xs">
                </div>
                <div>
                    <label class="label">Ketik Konfirmasi: <span class="text-orange-400 font-mono">{{ $phrase }}</span></label>
                    <input type="text" name="confirmation" placeholder="{{ $phrase }}" required class="input font-mono text-xs">
                </div>
                <button type="submit" class="btn btn-danger btn-block">Pulihkan Database</button>
            </form>
        </div>
    </div>

    <!-- Backup History Table -->
    <div class="lg:col-span-2 glass p-6 space-y-4">
        <div class="orn-line mb-1"></div>
        <h2 class="text-base font-extrabold text-white">Riwayat Arsip Backup Database</h2>
        <div class="tbl-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Nama Berkas</th>
                        <th>Tipe</th>
                        <th>Ukuran</th>
                        <th>Status</th>
                        <th>Waktu</th>
                        <th class="text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($backups as $b)
                        <tr>
                            <td class="font-mono text-xs font-bold text-white">{{ $b->filename }}</td>
                            <td>
                                <span class="badge {{ $b->backup_type === 'auto_scheduled' ? 'badge-info' : 'badge-orange' }}">
                                    {{ $b->backup_type === 'auto_scheduled' ? 'Otomatis' : 'Manual' }}
                                </span>
                            </td>
                            <td class="text-xs">{{ $b->humanSize() }}</td>
                            <td>
                                @if($b->status === 'success')
                                    <span class="badge badge-success">Sukses</span>
                                @else
                                    <span class="badge badge-danger" title="{{ $b->error_message }}">Gagal</span>
                                @endif
                            </td>
                            <td class="text-xs text-slate-400">{{ $b->created_at->format('d/m/Y H:i') }}</td>
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    @if($b->status === 'success')
                                        <a href="{{ route('admin.backups.download', $b) }}" class="btn btn-ghost btn-sm">Unduh</a>
                                    @endif
                                    <form method="POST" action="{{ route('admin.backups.destroy', $b) }}"
                                          data-confirm="Hapus arsip backup {{ $b->filename }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-slate-400 py-8">Belum ada riwayat backup.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div>{{ $backups->links() }}</div>
    </div>
</div>
@endsection
