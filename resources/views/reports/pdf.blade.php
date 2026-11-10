<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }} - {{ $report['document_number'] }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #0f172a; margin: 18px; }
        .header-table { width: 100%; border-bottom: 2px solid #0e1e36; padding-bottom: 8px; margin-bottom: 12px; }
        .company-name { font-size: 14px; font-weight: bold; color: #0e1e36; text-transform: uppercase; }
        .company-sub { font-size: 9px; color: #475569; }
        .doc-meta { text-align: right; font-size: 9px; color: #334155; }
        .confidential { display: inline-block; background: #0e1e36; color: #ffffff; padding: 2px 6px; font-size: 8px; font-weight: bold; margin-top: 3px; }
        .report-title { text-align: center; font-size: 13px; font-weight: bold; text-transform: uppercase; color: #0e1e36; margin: 6px 0 2px; }
        .report-period { text-align: center; font-size: 9px; color: #475569; margin-bottom: 12px; }
        .summary-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .summary-table td { border: 1px solid #cbd5e1; background: #f8fafc; padding: 5px 8px; }
        .summary-label { font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .summary-val { font-size: 11px; font-weight: bold; color: #0e1e36; }
        .data-table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .data-table th { background: #0e1e36; color: #ffffff; border: 1px solid #0e1e36; padding: 5px 6px; font-size: 8.5px; text-transform: uppercase; }
        .data-table td { border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 9px; }
        .zebra { background: #f1f5f9; }
        .notes-box { border: 1px solid #cbd5e1; background: #f8fafc; padding: 8px 10px; margin-bottom: 18px; font-size: 9px; }
        .sig-table { width: 100%; margin-top: 22px; text-align: center; font-size: 9.5px; }
        .sig-line { border-top: 1px solid #334155; display: inline-block; padding-top: 3px; width: 150px; font-weight: bold; margin-top: 48px; }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td>
                <div class="company-name">{{ $report['company']['name'] }}</div>
                <div class="company-sub">{{ $report['company']['address'] }} | {{ $report['company']['contact'] }}</div>
            </td>
            <td class="doc-meta">
                <div><strong>{{ $report['document_number'] }}</strong></div>
                <div>Dicetak: {{ $report['printed_at'] }}</div>
                <div class="confidential">CONFIDENTIAL / INTERNAL USE ONLY</div>
            </td>
        </tr>
    </table>

    <div class="report-title">{{ $report['title'] }}</div>
    <div class="report-period">Periode: {{ $report['period_label'] }} ({{ $report['filters_label'] }})</div>

    <table class="summary-table">
        <tr>
            @foreach($report['summary'] as $s)
                <td>
                    <div class="summary-label">{{ $s['label'] }}</div>
                    <div class="summary-val">{{ $s['value'] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                @foreach($report['columns'] as $col)
                    <th style="text-align: {{ $col['align'] ?? 'left' }}">{{ $col['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($report['rows'] as $idx => $row)
                <tr class="{{ $idx % 2 === 1 ? 'zebra' : '' }}">
                    @foreach($report['columns'] as $col)
                        <td style="text-align: {{ $col['align'] ?? 'left' }}">{{ $row[$col['key']] ?? '-' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['columns']) }}" style="text-align: center; padding: 14px;">Tidak ada data pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="notes-box">
        <strong>Catatan Analitik & Rekomendasi Manajemen:</strong>
        <ol style="margin: 4px 0 0 16px; padding: 0;">
            @foreach($report['notes'] as $note)
                <li>{{ $note }}</li>
            @endforeach
        </ol>
    </div>

    <table class="sig-table">
        <tr>
            <td>
                <div>Dipersiapkan Oleh,</div>
                <div class="sig-line">Supervisor Operasional</div>
            </td>
            <td>
                <div>Diverifikasi Oleh,</div>
                <div class="sig-line">HRD Manager HSM</div>
            </td>
            <td>
                <div>Disetujui Oleh,</div>
                <div class="sig-line">Direktur / Owner HSM</div>
            </td>
        </tr>
    </table>
</body>
</html>
