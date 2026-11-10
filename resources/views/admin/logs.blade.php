@extends('layouts.app', ['title' => 'Audit Trail & Log Aktivitas Sistem', 'subtitle' => 'Rekam jejak autentikasi, presensi, pengerjaan tugas, dan administrasi'])

@section('content')
<div class="glass p-5">
    <form method="GET" action="{{ route('admin.logs.index') }}" class="flex flex-wrap items-center gap-2">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari keterangan log..." class="input sm:max-w-xs">
        <select name="action" class="input sm:w-48">
            <option value="">Semua Kategori Aksi</option>
            @foreach($actions as $act)
                <option value="{{ $act }}" @selected(request('action') === $act)>{{ strtoupper($act) }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-ghost">Filter</button>
    </form>
</div>

<div class="table-surface">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Waktu Kejadian</th>
                    <th>Pengguna & Role</th>
                    <th>Kategori Aksi</th>
                    <th>Deskripsi Detail</th>
                    <th>Subjek Data</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td data-card-header="1" data-label="Waktu & Kategori Log">
                            <div class="flex items-center justify-between w-full gap-2">
                                <span class="font-mono text-xs font-bold text-orange-300">{{ $log->created_at->format('d/m/Y H:i:s') }}</span>
                                <span class="badge badge-info md:hidden">{{ $log->action }}</span>
                            </div>
                        </td>
                        <td data-label="Pengguna">
                            <div class="font-bold text-white">{{ $log->user?->name ?? 'Sistem Otomatis' }}</div>
                            @if($log->user)
                                <div class="text-[11px] text-slate-400">{{ $log->user->roleLabel() }}</div>
                            @endif
                        </td>
                        <td data-label="Kategori Aksi"><span class="badge badge-info">{{ $log->action }}</span></td>
                        <td data-label="Deskripsi Detail" class="text-xs text-slate-200">{{ $log->description }}</td>
                        <td data-label="Subjek Data" class="text-xs text-slate-400">{{ $log->subject_type ? class_basename($log->subject_type).' #'.$log->subject_id : '-' }}</td>
                        <td data-label="Alamat IP" class="font-mono text-xs text-slate-400">{{ $log->ip_address ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-slate-400 py-8">Tidak ada log ditemukan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
