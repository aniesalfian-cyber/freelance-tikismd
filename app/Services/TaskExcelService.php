<?php

namespace App\Services;

use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskExcelService
{
    /**
     * Hasilkan file Excel (.xlsx) resmi untuk format pengisian penugasan kurir.
     * Terdiri dari 2 lembar: Formulir Tugas & Referensi Kurir / Gerai.
     */
    public function generateTemplate(): StreamedResponse
    {
        $spreadsheet = $this->buildSpreadsheet();
        $fileName = 'Format_Import_Tugas_PT_HSM.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /**
     * Simpan template Excel ke direktori berkas lokal (berguna untuk testing/arsip).
     */
    public function saveTemplateToFile(string $path): string
    {
        $spreadsheet = $this->buildSpreadsheet();
        $writer = new Xlsx($spreadsheet);
        $writer->save($path);

        return $path;
    }

    /**
     * Konstruksi objek Spreadsheet dengan 2 sheet: Formulir Tugas & Referensi.
     */
    public function buildSpreadsheet(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('PT. Hubungan Saudara Mulia (HSM)')
            ->setTitle('Format Import Penugasan Kurir PT HSM')
            ->setDescription('Template resmi untuk import penugasan kurir operasional TIKISMD');

        // ==========================================
        // SHEET 1: FORMULIR TUGAS
        // ==========================================
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Formulir Tugas');

        // Header Perusahaan & Petunjuk
        $sheet->setCellValue('A1', 'PT. HUBUNGAN SAUDARA MULIA (HSM) — FORMAT IMPORT TUGAS KURIR');
        $sheet->setCellValue('A2', 'Petunjuk: Kolom dengan tanda (*) wajib diisi. Nomor HP Kurir harus terdaftar pada sistem.');
        $sheet->setCellValue('A3', 'Tipe Tugas: "pasti_harian" atau "tidak_pasti" | Format Tanggal: YYYY-MM-DD | Format Jam: HH:MM');

        $headers = [
            'A4' => 'No',
            'B4' => 'Nomor HP Kurir (*)',
            'C4' => 'Nama Kurir (Referensi)',
            'D4' => 'Divisi Operasional (Referensi)',
            'E4' => 'Kode Gerai (*)',
            'F4' => 'Judul Tugas (*)',
            'G4' => 'Deskripsi / Instruksi Tugas',
            'H4' => 'Tipe Tugas (*)',
            'I4' => 'Tanggal Penugasan (*)',
            'J4' => 'Jam Mulai (*)',
            'K4' => 'Durasi SLA (Menit)',
        ];

        foreach ($headers as $cell => $val) {
            $sheet->setCellValue($cell, $val);
        }

        // Contoh Data Realistis Sesuai 4 Divisi
        $tomorrow = today()->addDay()->toDateString();
        $sampleData = [
            [
                1,
                '081349497879',
                'Dedy Endra',
                'R4 Kurier Delivery',
                'PST-001',
                'Pengantaran Paket Mobil Box Rute Samarinda Ilir',
                'Antar paket kargo box besar ke alamat penerima & gerai transit',
                'pasti_harian',
                $tomorrow,
                '09:00',
                180,
            ],
            [
                2,
                '085654207395',
                'Hendra Saputra',
                'R4 Gerai, Corporate & Delivery',
                'PST-001',
                'Shuttle & Pickup Gerai Cabang TIKISMD',
                'Pengambilan paket dari gerai cabang dan serah terima gudang',
                'pasti_harian',
                $tomorrow,
                '10:00',
                120,
            ],
            [
                3,
                '081347457604',
                'Hendra Wahyudi',
                'R2 Delivery',
                'PST-001',
                'Delivery Paket Kurir Motor Sesi Pagi',
                'Antar paket retail area perumahan Vorvo & Juanda',
                'pasti_harian',
                $tomorrow,
                '08:30',
                180,
            ],
            [
                4,
                '081347850510',
                'Mardiansyah',
                'R2 Pickup Corporate',
                'PST-001',
                'Pickup Dokumen & Paket Rekanan Corporate Mitra',
                'Ambil paket berkas instansi corporate rekanan TIKISMD',
                'pasti_harian',
                $tomorrow,
                '13:00',
                120,
            ],
        ];

        $rIdx = 5;
        foreach ($sampleData as $row) {
            $cIdx = 1;
            foreach ($row as $val) {
                $coord = Coordinate::stringFromColumnIndex($cIdx).$rIdx;
                // Simpan nomor HP sebagai teks eksplisit agar awalan 0 tidak hilang
                if ($cIdx === 2) {
                    $sheet->setCellValueExplicit($coord, (string) $val, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coord, $val);
                }
                $cIdx++;
            }
            $rIdx++;
        }

        // Styling Sheet 1
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setARGB('FF0F172A');
        $sheet->getStyle('A2:A3')->getFont()->setSize(9.5)->setItalic(true)->getColor()->setARGB('FF475569');

        $headerRange = 'A4:K4';
        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0E1E36']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(28);

        $dataRange = 'A5:K8';
        $sheet->getStyle($dataRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'font' => ['size' => 10],
        ]);

        // Auto width Sheet 1
        foreach (range(1, 11) as $c) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        // ==========================================
        // SHEET 2: REFERENSI KURIR & GERAI
        // ==========================================
        $refSheet = $spreadsheet->createSheet();
        $refSheet->setTitle('Referensi Kurir & Gerai');

        $refSheet->setCellValue('A1', 'DAFTAR PERSONEL KURIR AKTIF PT. HSM (GUNAKAN NOMOR HP UNTUK INPUT)');
        $refSheet->setCellValue('A2', 'Tabel referensi resmi untuk memastikan Nomor HP kurir sesuai dengan sistem.');

        $courierHeaders = ['A4' => 'No', 'B4' => 'Nama Personel', 'C4' => 'Nomor HP (Kunci)', 'D4' => 'NIK', 'E4' => 'Divisi Operasional', 'F4' => 'Armada Kendaraan', 'G4' => 'Lingkup Tugas'];
        foreach ($courierHeaders as $cell => $val) {
            $refSheet->setCellValue($cell, $val);
        }

        $couriers = User::freelancers()->active()->orderBy('specialization')->orderBy('name')->get();
        $cRow = 5;
        foreach ($couriers as $i => $cr) {
            $refSheet->setCellValue('A'.$cRow, $i + 1);
            $refSheet->setCellValue('B'.$cRow, $cr->name);
            $refSheet->setCellValueExplicit('C'.$cRow, (string) $cr->phone, DataType::TYPE_STRING);
            $refSheet->setCellValue('D'.$cRow, $cr->nik ?: '-');
            $refSheet->setCellValue('E'.$cRow, $cr->specialization);
            $refSheet->setCellValue('F'.$cRow, $cr->vehicleName());
            $refSheet->setCellValue('G'.$cRow, $cr->operationalScope());
            $cRow++;
        }

        $courierHeaderRange = 'A4:G4';
        $refSheet->getStyle($courierHeaderRange)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF0E1E36']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $courierDataRange = 'A5:G'.($cRow - 1);
        $refSheet->getStyle($courierDataRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'font' => ['size' => 9.5],
        ]);

        // Tabel Gerai
        $gStart = $cRow + 2;
        $refSheet->setCellValue('A'.$gStart, 'DAFTAR TITIK GERAI / KANTOR HSM SAMARINDA');
        $refSheet->getStyle('A'.$gStart)->getFont()->setBold(true)->setSize(11);

        $gHeaderRow = $gStart + 1;
        $outletHeaders = ['A'.$gHeaderRow => 'No', 'B'.$gHeaderRow => 'Kode Gerai (Kunci)', 'C'.$gHeaderRow => 'Nama Gerai', 'D'.$gHeaderRow => 'Alamat Lokasi', 'E'.$gHeaderRow => 'Kategori'];
        foreach ($outletHeaders as $cell => $val) {
            $refSheet->setCellValue($cell, $val);
        }
        $refSheet->getStyle("A{$gHeaderRow}:E{$gHeaderRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF254D87']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $outlets = Outlet::active()->orderBy('is_main_office', 'desc')->orderBy('name')->get();
        $oRow = $gHeaderRow + 1;
        foreach ($outlets as $idx => $out) {
            $refSheet->setCellValue('A'.$oRow, $idx + 1);
            $refSheet->setCellValueExplicit('B'.$oRow, (string) $out->code, DataType::TYPE_STRING);
            $refSheet->setCellValue('C'.$oRow, $out->name);
            $refSheet->setCellValue('D'.$oRow, $out->address ?: 'Kota Samarinda');
            $refSheet->setCellValue('E'.$oRow, $out->is_main_office ? 'Kantor Utama (Pusat)' : 'Gerai Cabang');
            $oRow++;
        }

        $outletDataRange = "A{$gHeaderRow}:E".($oRow - 1);
        $refSheet->getStyle($outletDataRange)->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFCBD5E1']]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'font' => ['size' => 9.5],
        ]);

        // Auto width Sheet 2
        foreach (range(1, 7) as $c) {
            $refSheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        // Set active kembali ke Sheet 1
        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Parse file Excel yang diunggah dan simpan penugasan ke database.
     *
     * @return array [success: bool, imported: int, errors: array, total_rows: int]
     */
    public function importTasks(UploadedFile $file, int $creatorId): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheet(0); // Ambil sheet pertama (Formulir Tugas)
        $highestRow = $sheet->getHighestRow();

        if ($highestRow < 5) {
            return [
                'success' => false,
                'imported' => 0,
                'total_rows' => 0,
                'errors' => ['File Excel tidak memuat baris data penugasan (minimal baris ke-5).'],
            ];
        }

        $imported = 0;
        $rowErrors = [];
        $outlets = Outlet::all()->keyBy('code');
        $mainOffice = Outlet::mainOffice() ?? Outlet::first();
        $couriers = User::freelancers()->get();

        // Siapkan lookup map untuk kurir (nomor HP berbagai varian, NIK, nama)
        $courierMap = [];
        foreach ($couriers as $u) {
            $cleanPhone = preg_replace('/\D+/', '', (string) $u->phone);
            if ($cleanPhone) {
                $courierMap[$cleanPhone] = $u;
                if (str_starts_with($cleanPhone, '0')) {
                    $courierMap['62'.substr($cleanPhone, 1)] = $u;
                } elseif (str_starts_with($cleanPhone, '62')) {
                    $courierMap['0'.substr($cleanPhone, 2)] = $u;
                }
            }
            if ($u->nik) {
                $courierMap[mb_strtoupper(trim($u->nik))] = $u;
            }
            $courierMap[mb_strtolower(trim($u->name))] = $u;
        }

        $tasksToCreate = [];

        for ($row = 5; $row <= $highestRow; $row++) {
            $rawPhone = trim((string) $sheet->getCell('B'.$row)->getValue());
            $title = trim((string) $sheet->getCell('F'.$row)->getValue());

            // Baris kosong, abaikan
            if ($rawPhone === '' && $title === '') {
                continue;
            }

            // Validasi Kurir
            $cleanPhone = preg_replace('/\D+/', '', $rawPhone);
            $matchedUser = $courierMap[$cleanPhone] ?? null;

            // Coba fallback dengan Nama Kurir di kolom C jika no HP tidak match
            if (! $matchedUser) {
                $rawName = mb_strtolower(trim((string) $sheet->getCell('C'.$row)->getValue()));
                if ($rawName && isset($courierMap[$rawName])) {
                    $matchedUser = $courierMap[$rawName];
                }
            }

            if (! $matchedUser) {
                $rowErrors[] = "Baris {$row}: Kurir dengan No. HP '{$rawPhone}' tidak ditemukan pada sistem.";
                continue;
            }

            // Validasi Judul
            if ($title === '') {
                $rowErrors[] = "Baris {$row}: Judul Tugas wajib diisi.";
                continue;
            }

            // Validasi Gerai Lokasi
            $rawOutletCode = trim((string) $sheet->getCell('E'.$row)->getValue());
            $matchedOutlet = $outlets->get($rawOutletCode);
            if (! $matchedOutlet && $rawOutletCode !== '') {
                // Cari partial nama gerai
                $matchedOutlet = $outlets->first(fn ($o) => str_contains(mb_strtolower($o->name), mb_strtolower($rawOutletCode)));
            }
            $matchedOutlet ??= $mainOffice;
            if (! $matchedOutlet) {
                $rowErrors[] = "Baris {$row}: Lokasi gerai tidak valid dan kantor utama tidak tersedia.";
                continue;
            }

            // Validasi Tanggal
            $rawDate = $sheet->getCell('I'.$row)->getValue();
            $assignedDate = $this->parseDateValue($rawDate);
            if (! $assignedDate) {
                $rowErrors[] = "Baris {$row}: Format tanggal penugasan tidak valid (gunakan YYYY-MM-DD).";
                continue;
            }

            // Validasi Jam Mulai
            $rawTime = $sheet->getCell('J'.$row)->getValue();
            $scheduledTime = $this->parseTimeValue($rawTime);

            // Validasi Tipe Tugas
            $rawType = trim((string) $sheet->getCell('H'.$row)->getValue());
            $taskType = in_array($rawType, [TaskAssignment::TYPE_FIXED, TaskAssignment::TYPE_ADHOC], true)
                ? $rawType
                : TaskAssignment::TYPE_FIXED;

            // Durasi SLA
            $rawDuration = (int) $sheet->getCell('K'.$row)->getValue();
            $duration = ($rawDuration >= 5 && $rawDuration <= 720) ? $rawDuration : 30;

            $description = trim((string) $sheet->getCell('G'.$row)->getValue());

            $tasksToCreate[] = [
                'row' => $row,
                'user_id' => $matchedUser->id,
                'outlet_id' => $matchedOutlet->id,
                'title' => $title,
                'description' => $description ?: null,
                'task_type' => $taskType,
                'assigned_date' => $assignedDate,
                'scheduled_start_time' => $scheduledTime,
                'estimated_duration_minutes' => $duration,
                'status' => TaskAssignment::STATUS_PENDING,
                'created_by' => $creatorId,
            ];
        }

        if (empty($tasksToCreate)) {
            return [
                'success' => false,
                'imported' => 0,
                'total_rows' => count($tasksToCreate) + count($rowErrors),
                'errors' => $rowErrors ?: ['Tidak ada baris data tugas yang valid untuk diimpor.'],
            ];
        }

        // Eksekusi insert dalam transaksi database
        DB::transaction(function () use ($tasksToCreate, &$imported) {
            foreach ($tasksToCreate as $tData) {
                $dateObj = Carbon::parse($tData['assigned_date']);
                unset($tData['row']);
                $task = TaskAssignment::create($tData + [
                    'task_number' => TaskAssignment::generateNumber($dateObj),
                ]);
                \App\Models\AppNotification::notifyTaskAssigned($task);
                $imported++;
            }
        });

        return [
            'success' => true,
            'imported' => $imported,
            'total_rows' => $imported + count($rowErrors),
            'errors' => $rowErrors,
        ];
    }

    protected function parseDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return today()->toDateString();
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value))->toDateString();
        }

        try {
            return Carbon::parse(trim((string) $value))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseTimeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '09:00:00';
        }

        if (is_numeric($value)) {
            // Excel time disimpan sebagai fraksi dari 1 hari (0.5 = 12:00)
            $seconds = (int) round($value * 86400);
            $h = floor($seconds / 3600);
            $m = floor(($seconds % 3600) / 60);

            return sprintf('%02d:%02d:00', $h, $m);
        }

        $str = trim((string) $value);
        if (preg_match('/^(\d{1,2}):(\d{2})/', $str, $matches)) {
            return sprintf('%02d:%02d:00', (int) $matches[1], (int) $matches[2]);
        }

        return '09:00:00';
    }
}
