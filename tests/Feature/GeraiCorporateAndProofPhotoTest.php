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

    public function test_courier_can_complete_task_with_camera_proof_and_auto_compression(): void
    {
        Storage::fake('public');
        $courier = User::freelancers()->first();
        $outlet = Outlet::active()->branches()->first();

        // Buat tugas in_progress
        $task = TaskAssignment::create([
            'task_number' => 'TSK-TEST-CAM01',
            'user_id' => $courier->id,
            'outlet_id' => $outlet->id,
            'title' => 'Pengantaran Paket Hasil Jepretan Kamera',
            'description' => 'Uji coba upload kamera langsung',
            'task_type' => TaskAssignment::TYPE_ADHOC,
            'assigned_date' => today(),
            'scheduled_start_time' => '08:00:00',
            'estimated_duration_minutes' => 60,
            'status' => TaskAssignment::STATUS_IN_PROGRESS,
            'started_at' => now()->subMinutes(15),
            'start_latitude' => $outlet->latitude,
            'start_longitude' => $outlet->longitude,
            'start_distance_meters' => 5,
            'created_by' => User::where('role', User::ROLE_HRD)->first()->id,
        ]);

        // Simulasikan foto resolusi tinggi dari kamera smartphone (2000 x 1500 px)
        $largePhoto = UploadedFile::fake()->image('camera_capture.jpg', 2000, 1500);

        $res = $this->actingAs($courier)->post(route('pwa.tasks.complete', $task), [
            'latitude' => $outlet->latitude,
            'longitude' => $outlet->longitude,
            'accuracy' => 10,
            'notes' => 'Paket berhasil diserahterimakan dengan bukti jepretan kamera.',
            'proof' => $largePhoto,
        ], ['Accept' => 'application/json']);

        $res->assertOk();
        $res->assertJson(['ok' => true]);

        $task->refresh();
        $this->assertEquals(TaskAssignment::STATUS_COMPLETED, $task->status);
        $this->assertNotNull($task->proof_image);

        // Pastikan tersimpan di disk public
        Storage::disk('public')->assertExists($task->proof_image);

        // Jika GD tersedia, pastikan dimensi gambar telah di-resize agar max dimension <= 1280px
        if (extension_loaded('gd')) {
            $path = Storage::disk('public')->path($task->proof_image);
            $size = getimagesize($path);
            $this->assertNotFalse($size);
            $this->assertLessThanOrEqual(1280, $size[0]);
            $this->assertLessThanOrEqual(1280, $size[1]);
        }
    }

    public function test_multiple_outlets_can_share_same_location_code(): void
    {
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();

        $this->actingAs($hrd)->post(route('hrd.outlets.store'), [
            'code' => 'CORPT-SHARED-01',
            'name' => 'Tambang Damai Unit 1',
            'type' => Outlet::TYPE_CORPORATE,
            'address' => 'Jl.Poros Samarinda Blok A',
            'latitude' => -0.503100,
            'longitude' => 117.151100,
            'radius_meters' => 50,
            'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('hrd.outlets.index'));

        $this->actingAs($hrd)->post(route('hrd.outlets.store'), [
            'code' => 'CORPT-SHARED-01',
            'name' => 'Tambang Damai Unit 2',
            'type' => Outlet::TYPE_CORPORATE,
            'address' => 'Jl. Poros Samarinda Blok B',
            'latitude' => -0.503500,
            'longitude' => 117.151500,
            'radius_meters' => 50,
            'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('hrd.outlets.index'));

        $this->assertSame(2, Outlet::where('code', 'CORPT-SHARED-01')->count());
    }

    public function test_tasks_index_renders_corporate_card_icon_actions_and_detail_back_preserves_filters(): void
    {
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();
        $courier = User::freelancers()->firstOrFail();

        $corp = Outlet::create([
            'code' => 'CORPT-SMD010025101',
            'name' => 'TAMBANG DAMAI',
            'type' => Outlet::TYPE_CORPORATE,
            'address' => 'Samarinda',
            'latitude' => -0.504000,
            'longitude' => 117.154000,
            'radius_meters' => 50,
            'is_main_office' => false,
            'is_active' => true,
        ]);

        $task = TaskAssignment::create([
            'task_number' => 'TSK-CORP-0001',
            'user_id' => $courier->id,
            'outlet_id' => $corp->id,
            'title' => 'Pickup Dokumen Tambang Damai',
            'task_type' => TaskAssignment::TYPE_ADHOC,
            'assigned_date' => today(),
            'scheduled_start_time' => '10:00:00',
            'estimated_duration_minutes' => 45,
            'status' => TaskAssignment::STATUS_PENDING,
            'created_by' => $hrd->id,
        ]);

        $filteredUrl = route('hrd.tasks.index', [
            'date' => today()->toDateString(),
            'outlet_id' => $corp->id,
            'q' => 'Tambang',
        ]);

        $indexRes = $this->actingAs($hrd)->get($filteredUrl);
        $indexRes->assertOk()
            ->assertSee('TAMBANG DAMAI')
            ->assertSee('CORPT-SMD010025101')
            ->assertSee('>Corporate</span>', false)
            ->assertSee('aria-label="Detail tugas TSK-CORP-0001"', false)
            ->assertSee('aria-label="Edit tugas TSK-CORP-0001"', false)
            ->assertDontSee('>Detail & Peta<', false);

        // Buka detail tugas lalu pastikan tombol Kembali mengarah ke URL terfilter sebelumnya
        $detailRes = $this->actingAs($hrd)->get(route('hrd.tasks.show', $task));
        $detailRes->assertOk()
            ->assertSee('Kembali')
            ->assertSee(e($filteredUrl), false);
    }

    public function test_owner_can_login_with_phone_08115557089_and_pin_1478(): void
    {
        $res = $this->post('/login', [
            'login_mode' => 'pin',
            'login' => '08115557089',
            'pin' => '1478',
        ]);

        $res->assertRedirect('/owner/executive-dashboard');
        $this->assertAuthenticated();
        $this->assertSame(User::ROLE_OWNER, auth()->user()->role);
    }
}
