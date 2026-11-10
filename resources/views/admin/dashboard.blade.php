@extends('layouts.app', ['title' => 'Control Center SuperAdmin', 'subtitle' => 'Manajemen pengguna, konfigurasi sistem, audit log, dan cadangan database'])

@section('content')
<!-- Top Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 rise">
    @foreach(\App\Models\User::ROLES as $roleKey => $roleLabel)
        @php $rc = $roleCounts->get($roleKey); @endphp
        <div class="glass glass-hover p-5">
            <div class="flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">{{ $roleLabel }}</span>
                <span class="badge badge-orange">{{ (int) ($rc->active ?? 0) }} aktif</span>
            </div>
            <div class="mt-3 text-3xl font-extrabold text-white">{{ (int) ($rc->c ?? 0) }}</div>
            <div class="mt-1 text-xs text-slate-400">Total terdaftar</div>
        </div>
    @endforeach
</div>

<!-- Operational & Backup Quick Status -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 rise rise-2">
    <div class="glass p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <div class="orn-line mb-2"></div>
                <h2 class="text-base font-extrabold text-white">Status Cadangan Database</h2>
            </div>
            <x-icon name="database" class="w-6 h-6 text-orange-400" />
        </div>
        @if($lastBackup)
            <div class="p-3.5 rounded-2xl bg-navy-950/70 border border-white/10 text-xs space-y-1.5">
                <div class="font-mono font-bold text-emerald-300 truncate">{{ $lastBackup->filename }}</div>
                <div class="flex justify-between text-slate-400">
                    <span>Ukuran: {{ $lastBackup->humanSize() }}</span>
                    <span>{{ $lastBackup->created_at->diffForHumans() }}</span>
                </div>
            </div>
        @else
            <p class="text-xs text-slate-400">Belum ada arsip backup database tercatat.</p>
        @endif

        <div class="flex flex-wrap gap-2">
            <form method="POST" action="{{ route('admin.backups.store') }}" class="flex-1">
                @csrf
                <button type="submit" class="btn btn-primary btn-block btn-sm">
                    <x-icon name="database" class="w-4 h-4" /> Backup Sekarang
                </button>
            </form>
            <a href="{{ route('admin.backups.index') }}" class="btn btn-ghost btn-sm">Kelola ({{ $backupCount }})</a>
        </div>
    </div>

    <div class="glass p-6 space-y-4">
        <div class="orn-line mb-2"></div>
        <h2 class="text-base font-extrabold text-white">Kesehatan Operasional Hari Ini</h2>
        <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/10">
                <div class="text-slate-400">Gerai Aktif</div>
                <div class="text-xl font-extrabold text-white mt-1">{{ $stats['outlets'] }}</div>
            </div>
            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/10">
                <div class="text-slate-400">Tugas Hari Ini</div>
                <div class="text-xl font-extrabold text-white mt-1">{{ $stats['tasks_today'] }}</div>
            </div>
            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/10">
                <div class="text-slate-400">Presensi Masuk</div>
                <div class="text-xl font-extrabold text-emerald-400 mt-1">{{ $stats['attendance_today'] }}</div>
            </div>
            <div class="p-3 rounded-xl bg-white/[0.03] border border-white/10">
                <div class="text-slate-400">Akun Nonaktif</div>
                <div class="text-xl font-extrabold text-amber-400 mt-1">{{ $stats['inactive_users'] }}</div>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 pt-1">
            <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm">+ Tambah Pengguna</a>
            <a href="{{ route('admin.settings.edit') }}" class="btn btn-ghost btn-sm">Pengaturan Sistem</a>
            <a href="{{ route('owner.dashboard') }}" class="btn btn-ghost btn-sm">Executive Dashboard</a>
        </div>
    </div>

    <div class="glass p-6 space-y-3">
        <div class="orn-line mb-2"></div>
        <h2 class="text-base font-extrabold text-white">Permintaan Reset Password</h2>
        @forelse($resetRequests as $req)
            <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-between gap-2 text-xs">
                <div class="min-w-0">
                    <div class="font-bold text-white truncate">{{ $req->description }}</div>
                    <div class="text-[11px] text-slate-400">{{ $req->created_at->diffForHumans() }}</div>
                </div>
                @if($req->subject_id)
                    <form method="POST" action="{{ route('admin.users.reset', $req->subject_id) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">Reset</button>
                    </form>
                @endif
            </div>
        @empty
            <p class="text-xs text-slate-400 py-6 text-center">Tidak ada permintaan reset password tertunda.</p>
        @endforelse
    </div>
</div>

<!-- Recent Audit Logs -->
<div class="glass p-6 space-y-4 rise rise-3">
    <div class="flex items-center justify-between">
        <div>
            <div class="orn-line mb-2"></div>
            <h2 class="text-base font-extrabold text-white">Log Aktivitas Terbaru</h2>
        </div>
        <a href="{{ route('admin.logs.index') }}" class="btn btn-ghost btn-sm">Lihat Semua Log</a>
    </div>
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Aksi</th>
                    <th>Keterangan</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentLogs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-xs text-slate-400">{{ $log->created_at->format('d/m H:i:s') }}</td>
                        <td class="font-semibold text-white">{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td><span class="badge badge-info">{{ $log->action }}</span></td>
                        <td class="text-xs">{{ $log->description }}</td>
                        <td class="font-mono text-xs text-slate-400">{{ $log->ip_address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-400 py-6">Belum ada log aktivitas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
