<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeraiCorporateAndProofPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_menu_contains_gerai_and_corporate_label(): void
    {
        $hrd = User::where('role', User::ROLE_HRD)->first();
        $menu = Menu::for($hrd);
        $outletMenu = collect($menu)->firstWhere('route', 'hrd.outlets.index');

        $this->assertNotNull($outletMenu);
        $this->assertEquals('Gerai & Corporate', $outletMenu['label']);
    }

    public function test_can_create_and_filter_corporate_outlet(): void
    {
        $hrd = User::where('role', User::ROLE_HRD)->first();

        // Create Corporate partner
        $res = $this->actingAs($hrd)->post(route('hrd.outlets.store'), [
            'code' => 'CORP-TEST01',
            'name' => 'PT. Mitra Niaga Logistik',
            'type' => Outlet::TYPE_CORPORATE,
            'address' => 'Jl. Pelabuhan Samarinda No. 88',
            'latitude' => -0.505500,
            'longitude' => 117.155500,
            'radius_meters' => 60,
            'is_active' => '1',
        ]);

        $res->assertRedirect(route('hrd.outlets.index'));
        $this->assertDatabaseHas('outlets', [
            'code' => 'CORP-TEST01',
            'type' => Outlet::TYPE_CORPORATE,
            'name' => 'PT. Mitra Niaga Logistik',
        ]);

        $outlet = Outlet::where('code', 'CORP-TEST01')->first();
        $this->assertTrue($outlet->isCorporate());
        $this->assertFalse($outlet->isGerai());
        $this->assertEquals('Corporate (Rekan)', $outlet->typeLabel());

        // Test filtering by type=corporate
        $resIndex = $this->actingAs($hrd)->get(route('hrd.outlets.index', ['type' => 'corporate']));
        $resIndex->assertOk();
        $resIndex->assertSee('PT. Mitra Niaga Logistik');
        $resIndex->assertSee('Corporate Rekan');
    }

    public function test_task_proof_photo_is_rendered_with_full_size_link(): void
    {
        Storage::fake('public');
        $file = UploadedFile::fake()->image('test_proof.jpg', 600, 600);
        $path = $file->store('proofs/2026/11', 'public');

        $hrd = User::where('role', User::ROLE_HRD)->first();
        $courier = User::freelancers()->first();
        $corporate = Outlet::create([
            'code' => 'CORP-BANK01',
            'name' => 'Bank Mandiri Cabang Samarinda',
            'type' => Outlet::TYPE_CORPORATE,
            'address' => 'Jl. P. Irian No. 1',
            'latitude' => -0.501234,
            'longitude' => 117.152345,
            'radius_meters' => 50,
            'is_main_office' => false,
            'is_active' => true,
        ]);

        $task = TaskAssignment::create([
            'task_number' => 'TSK-TEST-9999',
            'user_id' => $courier->id,
            'outlet_id' => $corporate->id,
            'title' => 'Pickup Dokumen Corporate',
            'description' => 'Penjemputan berkas kerja sama',
            'task_type' => TaskAssignment::TYPE_ADHOC,
            'assigned_date' => today(),
            'scheduled_start_time' => '10:00:00',
            'estimated_duration_minutes' => 60,
            'status' => TaskAssignment::STATUS_COMPLETED,
            'actual_duration_minutes' => 45,
            'proof_image' => $path,
            'notes' => 'Tugas pickup corporate selesai rapi.',
            'created_by' => $hrd->id,
        ]);

        $this->assertNotNull($task->proofUrl());

        $res = $this->actingAs($hrd)->get(route('hrd.tasks.show', $task));
        $res->assertOk();
        $res->assertSee('Foto Bukti Pengerjaan');
        $res->assertSee('Buka Ukuran Penuh / Unduh');
        $res->assertSee($task->proofUrl());
        $res->assertSee('Corporate');
    }
}
