<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\TaskExcelService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TaskExcelImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_hrd_can_access_import_page_and_download_template(): void
    {
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();

        // 1. Visit import page
        $this->actingAs($hrd)
            ->get('/hrd/tasks/import')
            ->assertOk()
            ->assertSee('Import Data Penugasan Kurir')
            ->assertSee('Unduh Format Resmi Excel');

        // 2. Download template
        $response = $this->actingAs($hrd)
            ->get('/hrd/tasks/template');

        $response->assertOk();
        $this->assertTrue(str_contains(
            $response->headers->get('content-disposition', ''),
            'Format_Import_Tugas_PT_HSM.xlsx'
        ));
    }

    public function test_courier_cannot_access_import_or_download_template(): void
    {
        $courier = User::where('role', User::ROLE_FREELANCER)->firstOrFail();

        $this->actingAs($courier)
            ->get('/hrd/tasks/import')
            ->assertForbidden();

        $this->actingAs($courier)
            ->get('/hrd/tasks/template')
            ->assertForbidden();
    }

    public function test_excel_service_generates_valid_file_and_imports_rows(): void
    {
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();
        $courier = User::where('role', User::ROLE_FREELANCER)->firstOrFail();
        $outlet = Outlet::firstOrFail();

        $service = new TaskExcelService();
        $tempPath = storage_path('app/temp_test_template.xlsx');
        $service->saveTemplateToFile($tempPath);

        $this->assertFileExists($tempPath);

        // Upload the generated template (which has 4 example rows)
        $uploadedFile = new UploadedFile(
            $tempPath,
            'Format_Import_Tugas_PT_HSM.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $result = $service->importTasks($uploadedFile, $hrd->id);

        $this->assertTrue($result['success']);
        $this->assertGreaterThan(0, $result['imported']);
        $this->assertSame(0, $result['skipped_duplicates']);
        $this->assertEmpty($result['errors']);

        $initialTaskCount = TaskAssignment::count();
        $initialNotifCount = \App\Models\AppNotification::count();

        // Unggah ulang file yang persis sama: wajib 0 duplikat baru dan 0 notifikasi ganda.
        $reuploadFile = new UploadedFile(
            $tempPath,
            'Format_Import_Tugas_PT_HSM.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $secondResult = $service->importTasks($reuploadFile, $hrd->id);
        $this->assertTrue($secondResult['success']);
        $this->assertSame(0, $secondResult['imported']);
        $this->assertSame($result['imported'], $secondResult['skipped_duplicates']);
        $this->assertSame($initialTaskCount, TaskAssignment::count());
        $this->assertSame($initialNotifCount, \App\Models\AppNotification::count());

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }

    public function test_import_skips_in_file_and_db_duplicates_while_allowing_different_session_or_cancelled_replacement(): void
    {
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();
        $couriers = User::freelancers()->active()->orderBy('id')->take(2)->get();
        $courierA = $couriers[0];
        $courierB = $couriers[1];

        $branchOutlet = Outlet::where('is_main_office', false)->first() ?? Outlet::create([
            'code' => 'GERAI-TEST01',
            'name' => 'RAWA MAKMUR TEST',
            'type' => 'gerai',
            'address' => 'Samarinda',
            'latitude' => -0.502106,
            'longitude' => 117.153709,
            'radius_meters' => 100,
            'is_main_office' => false,
            'is_active' => true,
        ]);

        $targetDateObj = today()->addDays(2);
        $targetDate = $targetDateObj->toDateString();

        // Tugas pagi Courier A sudah terdaftar aktif di DB (huruf kapital).
        TaskAssignment::create([
            'task_number' => TaskAssignment::generateNumber($targetDateObj),
            'user_id' => $courierA->id,
            'outlet_id' => $branchOutlet->id,
            'title' => 'PICK UP GERAI R4 - Sesi Pagi',
            'task_type' => TaskAssignment::TYPE_FIXED,
            'assigned_date' => $targetDate,
            'scheduled_start_time' => '08:00:00',
            'estimated_duration_minutes' => 480,
            'status' => TaskAssignment::STATUS_PENDING,
            'created_by' => $hrd->id,
        ]);

        // Tugas Courier B sebelumnya sudah dibatalkan, sehingga boleh digantikan lewat import baru.
        TaskAssignment::create([
            'task_number' => TaskAssignment::generateNumber($targetDateObj),
            'user_id' => $courierB->id,
            'outlet_id' => $branchOutlet->id,
            'title' => 'PICK UP GERAI R2',
            'task_type' => TaskAssignment::TYPE_FIXED,
            'assigned_date' => $targetDate,
            'scheduled_start_time' => '09:00:00',
            'estimated_duration_minutes' => 180,
            'status' => TaskAssignment::STATUS_CANCELLED,
            'cancel_reason' => 'Salah jadwal',
            'created_by' => $hrd->id,
        ]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $rows = [
            // Baris 5: duplikat terhadap DB meski penulisan huruf berbeda ("Pick Up" vs "PICK UP") -> dilewati
            [1, $courierA->phone, $courierA->name, '', $branchOutlet->name, 'Pick Up Gerai R4 - Sesi Pagi', '', 'pasti_harian', $targetDate, '08:00', 480],
            // Baris 6: sesi siang (jam berbeda 14:00) di gerai yang sama -> diimpor
            [2, $courierA->phone, $courierA->name, '', $branchOutlet->name, 'Pick Up Gerai R4 - Sesi Siang', '', 'pasti_harian', $targetDate, '14:00', 480],
            // Baris 7: duplikat di dalam file Excel yang sama dengan baris 6 -> dilewati
            [3, $courierA->phone, $courierA->name, '', $branchOutlet->name, 'PICK UP GERAI R4 - Sesi Siang', '', 'pasti_harian', $targetDate, '14:00', 480],
            // Baris 8: menggantikan tugas Courier B yang berstatus cancelled -> diimpor
            [4, $courierB->phone, $courierB->name, '', $branchOutlet->name, 'Pickup Gerai R2', '', 'pasti_harian', $targetDate, '09:00', 180],
        ];

        $rIdx = 5;
        foreach ($rows as $r) {
            foreach ($r as $colZero => $val) {
                $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colZero + 1).$rIdx;
                if ($colZero === 1) {
                    $sheet->setCellValueExplicit($coord, (string) $val, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coord, $val);
                }
            }
            $rIdx++;
        }

        $tempPath = storage_path('app/temp_dedup_test.xlsx');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($tempPath);

        $upload = new UploadedFile(
            $tempPath,
            'Jadwal_Mixed.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($hrd)->post('/hrd/tasks/import', [
            'excel_file' => $upload,
        ]);

        // Redirect otomatis mengikuti tanggal yang ada di file Excel agar admin langsung melihat datanya.
        $response->assertRedirect(route('hrd.tasks.index', ['date' => $targetDate]));
        $response->assertSessionHas('success');
        $response->assertSessionHas('import_duplicates', fn (array $dups) => count($dups) === 2);

        // Courier A punya tepat 2 tugas aktif (08:00 lama + 14:00 baru), tidak ada ganda.
        $this->assertSame(
            2,
            TaskAssignment::where('user_id', $courierA->id)
                ->where('outlet_id', $branchOutlet->id)
                ->whereDate('assigned_date', $targetDate)
                ->where('status', '!=', TaskAssignment::STATUS_CANCELLED)
                ->count()
        );

        // Courier B punya 1 tugas aktif baru (menggantikan yang cancelled).
        $this->assertSame(
            1,
            TaskAssignment::where('user_id', $courierB->id)
                ->where('outlet_id', $branchOutlet->id)
                ->whereDate('assigned_date', $targetDate)
                ->where('status', TaskAssignment::STATUS_PENDING)
                ->count()
        );

        // Unggah ulang seluruh file kedua kalinya lewat HTTP: 0 tambahan, muncul warning.
        $uploadAgain = new UploadedFile(
            $tempPath,
            'Jadwal_Mixed.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $secondHttp = $this->actingAs($hrd)->post('/hrd/tasks/import', [
            'excel_file' => $uploadAgain,
        ]);

        $secondHttp->assertRedirect(route('hrd.tasks.index', ['date' => $targetDate]));
        $secondHttp->assertSessionHas('warning');

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
}
