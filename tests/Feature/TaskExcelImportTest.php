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

        $this->assertGreaterThan(0, $result['success']);
        $this->assertEmpty($result['errors']);

        // Verify task exists in database
        $this->assertTrue(
            TaskAssignment::where('created_by', $hrd->id)->exists()
        );

        if (file_exists($tempPath)) {
            unlink($tempPath);
        }
    }
}
