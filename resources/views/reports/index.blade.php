@extends('layouts.app', ['title' => 'Pusat Laporan Corporate Professional', 'subtitle' => 'Pratinjau dan ekspor dokumen resmi berstandar korporat (PDF & Excel)'])

@section('content')
<!-- Filter Builder -->
<div class="glass p-6 space-y-4">
    <div class="orn-line mb-1"></div>
    <h2 class="text-base font-extrabold text-white">Filter & Jenis Laporan Resmi</h2>

    <form method="GET" action="{{ route('reports.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
        <div class="lg:col-span-2">
            <label class="label">Jenis Dokumen Laporan</label>
            <select name="type" class="input" required>
                @foreach($types as $tk => $tl)
                    <option value="{{ $tk }}" @selected(($query['type'] ?? 'performa') === $tk)>{{ $tl }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Dari Tanggal</label>
            <input type="date" name="from" value="{{ $query['from'] ?? now()->startOfMonth()->toDateString() }}" class="input">
        </div>
        <div>
            <label class="label">Sampai Tanggal</label>
            <input type="date" name="to" value="{{ $query['to'] ?? now()->toDateString() }}" class="input">
        </div>
        <div>
            <label class="label">Gerai</label>
            <select name="outlet_id" class="input">
                <option value="">Semua Gerai</option>
                @foreach($outlets as $o)
                    <option value="{{ $o->id }}" @selected(($query['outlet_id'] ?? '') == $o->id)>{{ $o->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="label">Freelancer</label>
            <select name="user_id" class="input">
                <option value="">Semua Freelancer</option>
                @foreach($freelancers as $fl)
                    <option value="{{ $fl->id }}" @selected(($query['user_id'] ?? '') == $fl->id)>{{ $fl->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="lg:col-span-6 flex flex-wrap items-center justify-between gap-2 pt-2">
            <div class="flex items-center gap-2">
                <label class="text-xs text-slate-400">Filter Grade:</label>
                <select name="grade" class="input w-36 py-1.5 text-xs">
                    <option value="">Semua Grade</option>
                    @foreach(['A','B','C','D'] as $g)
                        <option value="{{ $g }}" @selected(($query['grade'] ?? '') === $g)>Grade {{ $g }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary">
                    Tampilkan Pratinjau Laporan
                </button>
                @if($report)
                    <a href="{{ route('reports.pdf', request()->query()) }}" class="btn btn-ghost">
                        Unduh PDF Resmi
                    </a>
                    <a href="{{ route('reports.excel', request()->query()) }}" class="btn btn-success">
                        Unduh Excel (.xlsx)
                    </a>
                @endif
            </div>
        </div>
    </form>
</div>

@if($report)
    <!-- Corporate Paper Preview -->
    <div class="report-paper p-6 sm:p-10 space-y-6 rise">
        <!-- Corporate Letterhead -->
        <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-slate-900 pb-4">
            <div class="flex items-center gap-3.5">
                <img src="/icons/icon-192.svg" alt="HSM Logo" class="w-12 h-12 rounded-xl border border-slate-200 shadow-sm flex-none" />
                <div>
                    <div class="text-lg font-extrabold text-slate-900 uppercase tracking-tight">{{ $report['company']['name'] }}</div>
                    <div class="text-xs text-slate-600">{{ $report['company']['address'] }} &bull; {{ $report['company']['contact'] }}</div>
                </div>
            </div>
            <div class="text-right text-xs text-slate-600 font-mono">
                <div class="font-bold text-slate-900">{{ $report['document_number'] }}</div>
                <div>Dicetak: {{ $report['printed_at'] }}</div>
                <div class="inline-block mt-1 px-2 py-0.5 rounded bg-slate-900 text-white text-[10px] font-bold">CONFIDENTIAL / INTERNAL USE ONLY</div>
            </div>
        </div>

        <!-- Title & Period -->
        <div class="text-center space-y-1">
            <h2 class="text-xl font-extrabold text-slate-900 uppercase tracking-wide">{{ $report['title'] }}</h2>
            <p class="text-xs text-slate-600">Periode Evaluasi: <b>{{ $report['period_label'] }}</b> ({{ $report['filters_label'] }})</p>
        </div>

        <!-- Executive Summary Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
            @foreach($report['summary'] as $s)
                <div class="p-3 rounded-lg bg-slate-100 border border-slate-200">
                    <div class="text-[10px] font-bold uppercase text-slate-500">{{ $s['label'] }}</div>
                    <div class="text-sm font-extrabold text-slate-900 mt-0.5">{{ $s['value'] }}</div>
                </div>
            @endforeach
        </div>

        <!-- Data Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-xs border-collapse border border-slate-300">
                <thead>
                    <tr class="bg-slate-900 text-white">
                        @foreach($report['columns'] as $col)
                            <th class="border border-slate-700 px-2.5 py-2 text-{{ $col['align'] ?? 'left' }} font-bold uppercase tracking-wider">
                                {{ $col['label'] }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $rIdx => $row)
                        <tr class="{{ $rIdx % 2 === 1 ? 'bg-slate-50' : 'bg-white' }}">
                            @foreach($report['columns'] as $col)
                                <td class="border border-slate-300 px-2.5 py-1.5 text-{{ $col['align'] ?? 'left' }} text-slate-800">
                                    {{ $row[$col['key']] ?? '-' }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($report['columns']) }}" class="text-center py-6 text-slate-500">
                                Tidak ada data pada periode yang dipilih.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Analytical Notes -->
        <div class="p-4 rounded-lg bg-slate-50 border border-slate-200 space-y-1 text-xs text-slate-700">
            <div class="font-bold text-slate-900 uppercase">Catatan Analitik & Rekomendasi Manajemen:</div>
            <ol class="list-decimal list-inside space-y-0.5">
                @foreach($report['notes'] as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ol>
        </div>

        <!-- Signature Block -->
        <div class="grid grid-cols-3 gap-6 pt-6 text-center text-xs text-slate-700">
            <div>
                <div class="font-semibold">Dipersiapkan Oleh,</div>
                <div class="h-16"></div>
                <div class="font-bold text-slate-900 border-t border-slate-400 pt-1 inline-block px-6">Supervisor Operasional</div>
            </div>
            <div>
                <div class="font-semibold">Diverifikasi Oleh,</div>
                <div class="h-16"></div>
                <div class="font-bold text-slate-900 border-t border-slate-400 pt-1 inline-block px-6">HRD Manager HSM</div>
            </div>
            <div>
                <div class="font-semibold">Disetujui Oleh,</div>
                <div class="h-16"></div>
                <div class="font-bold text-slate-900 border-t border-slate-400 pt-1 inline-block px-6">Direktur / Owner HSM</div>
            </div>
        </div>
    </div>
@else
    <div class="glass p-10 text-center space-y-3">
        <p class="text-sm text-slate-300">Pilih jenis dokumen dan rentang tanggal di atas, lalu klik <b>Tampilkan Pratinjau Laporan</b>.</p>
        <a href="{{ route('reports.index', ['type' => 'performa']) }}" class="btn btn-primary btn-sm">Muat Laporan Performa Bulan Ini</a>
    </div>
@endif
@endsection
