<?php

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\Outlet;
use App\Models\PerformanceLog;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HsmEndToEndTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_all_four_roles_redirect_to_their_respective_dashboards(): void
    {
        $map = [
            'superadmin@hsm.id' => '/admin/dashboard',
            'hrd@hsm.id' => '/hrd/dashboard',
            'freelancer@hsm.id' => '/pwa/workspace',
            'owner@hsm.id' => '/owner/executive-dashboard',
        ];

        foreach ($map as $email => $expectedPath) {
            $res = $this->post('/login', ['email' => $email, 'password' => 'Password123!']);
            $res->assertRedirect($expectedPath);
            $this->get($expectedPath)->assertOk();
            $this->post('/logout');
        }
    }

    public function test_geofencing_attendance_accepts_inside_radius_and_rejects_outside(): void
    {
        $freelancer = User::where('email', 'freelancer@hsm.id')->firstOrFail();
        $office = Outlet::mainOffice();

        // 1. Di luar radius (~180m) -> Ditolak (422)
        $this->actingAs($freelancer)
            ->postJson('/pwa/attendance/check-in', [
                'latitude' => $office->latitude + 0.0016,
                'longitude' => $office->longitude,
                'accuracy' => 10,
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        $this->assertDatabaseCount('attendances', 0);

        // 2. Di dalam radius (~15m) -> Diterima (200)
        $this->actingAs($freelancer)
            ->postJson('/pwa/attendance/check-in', [
                'latitude' => $office->latitude + 0.0001,
                'longitude' => $office->longitude,
                'accuracy' => 8,
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('attendances', [
            'user_id' => $freelancer->id,
            'outlet_id' => $office->id,
        ]);
    }

    public function test_task_lifecycle_blocks_without_attendance_then_tracks_3_states_and_scores_kpi(): void
    {
        $hrd = User::where('email', 'hrd@hsm.id')->firstOrFail();
        $freelancer = User::where('email', 'freelancer@hsm.id')->firstOrFail();
        $office = Outlet::mainOffice();
        $gerai = Outlet::where('code', 'GR-001')->firstOrFail();

        // HRD membuat tugas untuk freelancer hari ini
        $this->actingAs($hrd)->post('/hrd/tasks', [
            'user_ids' => [$freelancer->id],
            'outlet_id' => $gerai->id,
            'title' => 'Audit Stok Pagi Gerai Ilir',
            'description' => 'Cek seluruh rak utama',
            'task_type' => TaskAssignment::TYPE_FIXED,
            'assigned_date' => today()->toDateString(),
            'scheduled_start_time' => '09:00',
            'estimated_duration_minutes' => 30,
        ])->assertRedirect();

        $task = TaskAssignment::where('user_id', $freelancer->id)->firstOrFail();
        $this->assertSame('pending', $task->status);

        // Freelancer mencoba mulai sebelum presensi -> Ditolak
        $this->actingAs($freelancer)
            ->postJson("/pwa/tasks/{$task->id}/start", [
                'latitude' => $gerai->latitude,
                'longitude' => $gerai->longitude,
                'accuracy' => 10,
            ])
            ->assertStatus(422);

        // Presensi masuk di Kantor Utama
        $this->actingAs($freelancer)
            ->postJson('/pwa/attendance/check-in', [
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'accuracy' => 8,
            ])
            ->assertOk();

        // Mulai tugas (pending -> in_progress)
        $this->actingAs($freelancer)
            ->postJson("/pwa/tasks/{$task->id}/start", [
                'latitude' => $gerai->latitude + 0.00005,
                'longitude' => $gerai->longitude,
                'accuracy' => 10,
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'in_radius' => true]);

        $task->refresh();
        $this->assertSame('in_progress', $task->status);
        $this->assertNotNull($task->started_at);

        // Selesaikan tugas (in_progress -> completed)
        $this->actingAs($freelancer)
            ->postJson("/pwa/tasks/{$task->id}/complete", [
                'latitude' => $gerai->latitude + 0.00005,
                'longitude' => $gerai->longitude,
                'accuracy' => 10,
                'notes' => 'Pengecekan stok rak A sampai F selesai tanpa selisih.',
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'location_valid' => true]);

        $task->refresh();
        $this->assertSame('completed', $task->status);
        $this->assertTrue($task->is_location_valid);
        $this->assertNotNull($task->actual_duration_minutes);

        // Pastikan KPI harian otomatis terhitung
        $log = PerformanceLog::where('user_id', $freelancer->id)->whereDate('evaluation_date', today())->first();
        $this->assertNotNull($log);
        $this->assertGreaterThanOrEqual(80, $log->final_kpi_score);
    }

    public function test_owner_reports_and_superadmin_features_work(): void
    {
        $superadmin = User::where('email', 'superadmin@hsm.id')->firstOrFail();
        $hrd = User::where('email', 'hrd@hsm.id')->firstOrFail();
        $owner = User::where('email', 'owner@hsm.id')->firstOrFail();
        $freelancer = User::where('email', 'freelancer@hsm.id')->firstOrFail();

        // SuperAdmin pages & quick reset password
        foreach (['/admin/dashboard', '/admin/users', '/admin/users/create', '/admin/backups', '/admin/settings', '/admin/logs'] as $url) {
            $this->actingAs($superadmin)->get($url)->assertOk();
        }
        $this->actingAs($superadmin)
            ->post("/admin/users/{$freelancer->id}/reset-password")
            ->assertRedirect()
            ->assertSessionHas('credential');

        // HRD pages & daily task generation
        foreach (['/hrd/dashboard', '/hrd/outlets', '/hrd/outlets/create', '/hrd/freelancers', '/hrd/freelancers/create', '/hrd/templates', '/hrd/templates/create', '/hrd/tasks', '/hrd/tasks/create', '/hrd/attendances', '/hrd/performance'] as $url) {
            $this->actingAs($hrd)->get($url)->assertOk();
        }
        $this->actingAs($hrd)->post('/hrd/tasks/generate', ['date' => today()->toDateString()])->assertRedirect();

        // Freelancer PWA pages
        foreach (['/pwa/workspace', '/pwa/tasks', '/pwa/attendance', '/pwa/performance', '/profile'] as $url) {
            $this->actingAs($freelancer)->get($url)->assertOk();
        }

        // Owner Executive Dashboard & Corporate Reports (HTML, PDF, Excel)
        $this->actingAs($owner)->get('/owner/executive-dashboard')->assertOk();
        foreach (['performa', 'tugas', 'presensi'] as $type) {
            $this->actingAs($owner)->get("/reports?type={$type}")->assertOk();
        }
        $this->actingAs($owner)->get('/reports/pdf?type=performa')->assertOk();
        $this->actingAs($owner)->get('/reports/excel?type=performa')->assertOk();
    }
}
