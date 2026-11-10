@extends('layouts.app', ['title' => 'Template Tugas Pasti Harian', 'subtitle' => 'Daftar tugas rutin berulang yang dapat digenerate otomatis setiap hari'])

@section('content')
<div class="glass p-5 flex items-center justify-between gap-3">
    <p class="text-xs text-slate-300">
        Template tugas di bawah ini akan digenerate otomatis setiap pagi pukul 06:00 WIB atau melalui tombol <b>Generate Tugas Pasti</b> di menu Penugasan.
    </p>
    @if(auth()->user()->canManage())
        <a href="{{ route('hrd.templates.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="w-4 h-4" /> Buat Template Baru
        </a>
    @endif
</div>

<div class="table-surface">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Judul Tugas Rutin</th>
                    <th>Gerai Default</th>
                    <th>Penerima</th>
                    <th>Jam Mulai</th>
                    <th>Target SLA</th>
                    <th>Hari Aktif</th>
                    <th>Status</th>
                    @if(auth()->user()->canManage())
                        <th class="text-right">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $tpl)
                    <tr>
                        <td data-card-header="1" data-label="Template Tugas Pasti">
                            <div class="font-extrabold text-white text-sm">{{ $tpl->title }}</div>
                            <div class="text-xs text-slate-400 mt-0.5">{{ $tpl->description }}</div>
                        </td>
                        <td data-label="Gerai Lokasi" class="text-xs font-semibold text-white">{{ $tpl->outlet?->name ?? '-' }}</td>
                        <td data-label="Penerima Default" class="text-xs">{{ $tpl->user?->name ?? 'Semua Freelancer Aktif' }}</td>
                        <td data-label="Jam Target Mulai" class="font-mono text-xs text-orange-300 font-bold">{{ $tpl->startTimeShort() }}</td>
                        <td data-label="Target Durasi SLA"><span class="badge badge-info">{{ $tpl->estimated_duration_minutes }} menit</span></td>
                        <td data-label="Jadwal Hari Aktif" class="text-xs">{{ $tpl->activeDaysLabel() }}</td>
                        <td data-label="Status Template">
                            <span class="badge {{ $tpl->is_active ? 'badge-success' : 'badge-muted' }}">
                                {{ $tpl->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        @if(auth()->user()->canManage())
                            <td data-card-actions="1" data-label="Aksi Template" class="text-right">
                                <div class="flex justify-end gap-1.5">
                                    <a href="{{ route('hrd.templates.edit', $tpl) }}" class="btn btn-ghost btn-sm">Edit Template</a>
                                    <form method="POST" action="{{ route('hrd.templates.destroy', $tpl) }}"
                                          data-confirm="Hapus template {{ $tpl->title }}?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-slate-400 py-8">Belum ada template tugas harian.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
</div>
@endsection
