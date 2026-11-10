@extends('layouts.app', ['title' => 'Evaluasi & Indeks Kinerja (KPI Engine)', 'subtitle' => 'Perhitungan skor komposit berbobot penyelesaian, waktu SLA, lokasi, dan kehadiran'])

@section('content')
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 rise">
    <div class="glass p-5 flex items-center justify-between">
        <div>
            <div class="text-xs font-bold uppercase text-slate-400">Rata-rata Skor Periode</div>
            <div class="text-3xl font-extrabold text-white mt-1">{{ number_format($avg, 2) }}</div>
        </div>
        <span class="badge {{ \App\Models\PerformanceLog::badgeFor($avgGrade) }} text-sm px-3 py-1.5">Grade {{ $avgGrade }}</span>
    </div>
    <div class="lg:col-span-2 glass p-5 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="space-y-1">
            <div class="font-bold text-white">Formula Penilaian Aktif:</div>
            <div class="text-slate-300">
                Penyelesaian ({{ round($weights[0]*100) }}%) + Ketepatan SLA ({{ round($weights[1]*100) }}%) + Akurasi Lokasi ({{ round($weights[2]*100) }}%) + Kehadiran ({{ round($weights[3]*100) }}%)
            </div>
        </div>
        @if(auth()->user()->canManage())
            <form method="POST" action="{{ route('hrd.performance.recalculate') }}" class="flex items-center gap-2">
                @csrf
                <input type="hidden" name="from" value="{{ $from->toDateString() }}">
                <input type="hidden" name="to" value="{{ $to->toDateString() }}">
                <button type="submit" class="btn btn-primary btn-sm">
                    <x-icon name="repeat" class="w-4 h-4" /> Hitung Ulang Skor Periode Ini
                </button>
            </form>
        @endif
    </div>
</div>

<!-- Filter Bar -->
<div class="glass p-5">
    <form method="GET" action="{{ route('hrd.performance.index') }}" class="flex flex-wrap items-center gap-2">
        <input type="date" name="from" value="{{ $from->toDateString() }}" class="input sm:w-40">
        <input type="date" name="to" value="{{ $to->toDateString() }}" class="input sm:w-40">
        <select name="user_id" class="input sm:w-48">
            <option value="">Semua Freelancer</option>
            @foreach($freelancers as $fl)
                <option value="{{ $fl->id }}" @selected(request('user_id') == $fl->id)>{{ $fl->name }}</option>
            @endforeach
        </select>
        <select name="grade" class="input sm:w-36">
            <option value="">Semua Grade</option>
            @foreach(['A', 'B', 'C', 'D'] as $g)
                <option value="{{ $g }}" @selected(request('grade') === $g)>Grade {{ $g }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-ghost">Terapkan Filter</button>
    </form>
</div>

<!-- Performance Logs Table -->
<div class="table-surface">
    <div class="tbl-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Freelancer</th>
                    <th>Tugas (Selesai/Total)</th>
                    <th>Penyelesaian</th>
                    <th>Waktu SLA</th>
                    <th>Lokasi</th>
                    <th>Presensi</th>
                    <th>Skor Akhir</th>
                    <th>Grade</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $l)
                    <tr>
                        <td data-card-header="1" data-label="Tanggal Evaluasi & Freelancer">
                            <div class="flex items-center justify-between w-full gap-2">
                                <div>
                                    <div class="font-mono text-xs text-orange-300 font-bold">{{ $l->evaluation_date->format('d/m/Y') }}</div>
                                    <div class="font-extrabold text-white text-sm md:hidden mt-0.5">{{ $l->user?->name }}</div>
                                </div>
                                <span class="badge {{ \App\Models\PerformanceLog::badgeFor($l->grade) }} md:hidden">
                                    {{ number_format($l->final_kpi_score, 2) }} ({{ $l->grade }})
                                </span>
                            </div>
                        </td>
                        <td data-label="Freelancer & Catatan">
                            <div>
                                <div class="font-bold text-white hidden md:block">{{ $l->user?->name }}</div>
                                <div class="text-xs text-slate-300">{{ $l->remarks ?: 'Evaluasi harian otomatis' }}</div>
                            </div>
                        </td>
                        <td data-label="Penyelesaian Tugas" class="font-bold text-white">{{ $l->completed_tasks }} / {{ $l->total_tasks }} tugas</td>
                        <td data-label="Skor Penyelesaian" class="text-xs font-mono text-white">{{ number_format($l->completion_score, 1) }}</td>
                        <td data-label="Skor Ketepatan SLA" class="text-xs font-mono text-white">{{ number_format($l->time_score, 1) }}</td>
                        <td data-label="Skor Lokasi GPS" class="text-xs font-mono text-white">{{ number_format($l->location_score, 1) }}</td>
                        <td data-label="Skor Presensi" class="text-xs font-mono text-white">{{ number_format($l->attendance_score, 1) }}</td>
                        <td data-label="Skor Akhir KPI" class="font-mono font-extrabold text-orange-300">{{ number_format($l->final_kpi_score, 2) }}</td>
                        <td data-label="Predikat Grade"><span class="badge {{ \App\Models\PerformanceLog::badgeFor($l->grade) }}">Grade {{ $l->grade }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-center text-slate-400 py-8">Belum ada log penilaian pada rentang ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $logs->links() }}</div>
</div>
@endsection
