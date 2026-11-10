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

<div class="glass p-5">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Waktu</th>
                    <th>Pengguna</th>
                    <th>Aksi</th>
                    <th>Deskripsi</th>
                    <th>Subjek</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap font-mono text-xs text-slate-400">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="font-semibold text-white">{{ $log->user?->name ?? 'Sistem' }}</td>
                        <td><span class="badge badge-info">{{ $log->action }}</span></td>
                        <td class="text-xs">{{ $log->description }}</td>
                        <td class="text-xs text-slate-400">{{ $log->subject_type ? $log->subject_type.' #'.$log->subject_id : '-' }}</td>
                        <td class="font-mono text-xs text-slate-400">{{ $log->ip_address ?? '-' }}</td>
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
