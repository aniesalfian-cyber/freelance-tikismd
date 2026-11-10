@extends('layouts.app', ['title' => 'Data Master Karyawan Freelancer', 'subtitle' => 'Manajemen personel operasional, bidang keahlian, dan ringkasan KPI 30 hari'])

@section('content')
<div class="glass p-5 flex flex-wrap items-center justify-between gap-3">
    <form method="GET" action="{{ route('hrd.freelancers.index') }}" class="flex flex-wrap items-center gap-2 flex-1">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cari NIK, nama, email, WhatsApp..." class="input sm:max-w-xs">
        <select name="status" class="input sm:w-40">
            <option value="">Semua Status</option>
            <option value="active" @selected(request('status') === 'active')>Aktif</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Nonaktif</option>
        </select>
        <button type="submit" class="btn btn-ghost">Filter</button>
    </form>
    @if(auth()->user()->canManage())
        <a href="{{ route('hrd.freelancers.create') }}" class="btn btn-primary">
            <x-icon name="plus" class="w-4 h-4" /> Daftarkan Freelancer
        </a>
    @endif
</div>

<div class="glass p-5">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>NIK & Nama</th>
                    <th>Kontak</th>
                    <th>Bidang / Tarif</th>
                    <th>Tugas (30 Hari)</th>
                    <th>Rata-rata KPI</th>
                    <th>Status</th>
                    @if(auth()->user()->canManage())
                        <th class="text-right">Aksi</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse($freelancers as $fl)
                    @php
                        $kpi = round((float) $fl->kpi_30d, 2);
                        $grade = \App\Services\PerformanceScoringService::gradeFor($kpi);
                    @endphp
                    <tr>
                        <td>
                            <div class="font-bold text-white">{{ $fl->name }}</div>
                            <div class="font-mono text-xs text-orange-300">{{ $fl->nik ?: '-' }}</div>
                        </td>
                        <td class="text-xs">
                            <div class="text-white">{{ $fl->phone ?: '-' }}</div>
                            <div class="text-slate-400">{{ $fl->email }}</div>
                        </td>
                        <td class="text-xs">
                            <div class="font-semibold text-white">{{ $fl->specialization ?: 'Operasional Umum' }}</div>
                            <div class="text-slate-400">Rp {{ number_format((float) $fl->hourly_rate, 0, ',', '.') }}/jam</div>
                        </td>
                        <td class="font-bold text-white">{{ $fl->tasks_30d }} tugas</td>
                        <td>
                            @if($fl->kpi_30d !== null)
                                <span class="badge {{ \App\Models\PerformanceLog::badgeFor($grade) }}">
                                    {{ number_format($kpi, 2) }} ({{ $grade }})
                                </span>
                            @else
                                <span class="text-xs text-slate-500">Belum dinilai</span>
                            @endif
                        </td>
                        <td>
                            <span class="badge {{ $fl->is_active ? 'badge-success' : 'badge-danger' }}">
                                {{ $fl->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        @if(auth()->user()->canManage())
                            <td class="text-right">
                                <div class="flex justify-end gap-1.5 flex-wrap">
                                    <a href="{{ route('hrd.freelancers.show', $fl) }}" class="btn btn-ghost btn-sm">Detail</a>
                                    <a href="{{ route('hrd.freelancers.edit', $fl) }}" class="btn btn-ghost btn-sm">Edit</a>
                                    @if(auth()->user()->isSuperadmin())
                                        <form method="POST" action="{{ route('admin.users.reset', $fl) }}"
                                              data-confirm="Reset password {{ $fl->name }}?">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm">Reset Pass</button>
                                        </form>
                                    @else
                                        <form method="POST" action="{{ route('hrd.freelancers.request-reset', $fl) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-ghost btn-sm" title="Minta SuperAdmin mereset password">Minta Reset</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-slate-400 py-8">Belum ada data freelancer.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $freelancers->links() }}</div>
</div>
@endsection
