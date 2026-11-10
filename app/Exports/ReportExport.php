<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** Ekspor laporan generik ke Excel dengan kop korporat dan header navy. */
class ReportExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    protected int $headerRow;
    protected int $colCount;
    protected int $dataCount;

    public function __construct(protected array $report)
    {
        $this->colCount = count($report['columns']);
        $this->dataCount = count($report['rows']);
    }

    public function array(): array
    {
        $r = $this->report;
        $rows = [
            [$r['company']['name'].' — '.$r['company']['address']],
            [mb_strtoupper($r['title'])],
            ['Periode: '.$r['period_label'].'   |   '.$r['filters_label']],
            ['No. Dokumen: '.$r['document_number'].'   |   Dicetak: '.$r['printed_at'].'   |   RAHASIA / CONFIDENTIAL'],
            [],
        ];

        foreach (array_chunk($r['summary'], 3) as $chunk) {
            $line = [];
            foreach ($chunk as $s) {
                $line[] = $s['label'].': '.$s['value'];
            }
            $rows[] = $line;
        }
        $rows[] = [];

        $this->headerRow = count($rows) + 1;
        $rows[] = array_column($r['columns'], 'label');

        foreach ($r['rows'] as $row) {
            $rows[] = array_map(fn ($c) => $row[$c['key']] ?? '', $r['columns']);
        }

        $rows[] = [];
        $rows[] = ['Catatan Analitik & Rekomendasi:'];
        foreach ($r['notes'] as $i => $note) {
            $rows[] = [($i + 1).'. '.$note];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastCol = Coordinate::stringFromColumnIndex(max(1, $this->colCount));

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF0E1E36');
        $sheet->getStyle('A3:A4')->getFont()->setSize(10)->getColor()->setARGB('FF475569');

        $header = "A{$this->headerRow}:{$lastCol}{$this->headerRow}";
        $sheet->getStyle($header)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0E1E36']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $first = $this->headerRow + 1;
        $last = $this->headerRow + $this->dataCount;
        if ($this->dataCount > 0) {
            $sheet->getStyle("A{$first}:{$lastCol}{$last}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
                'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            ]);
            for ($row = $first; $row <= $last; $row++) {
                if (($row - $first) % 2 === 1) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                        ->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF1F5F9');
                }
            }
        }

        $sheet->getStyle('A'.($last + 2))->getFont()->setBold(true);
        $sheet->freezePane('A'.$first);

        return null;
    }

    public function title(): string
    {
        return mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', '', $this->report['title']), 0, 31);
    }
}
