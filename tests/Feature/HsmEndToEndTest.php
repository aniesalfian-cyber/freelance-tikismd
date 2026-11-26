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
            ['login' => 'superadmin@hsm.id', 'password' => '123456', 'path' => '/admin/dashboard'],
            ['login' => '085250815716', 'password' => '123456', 'path' => '/hrd/dashboard'], // Arif (Admin HRD)
            ['login' => '081349497879', 'password' => '123456', 'path' => '/pwa/workspace'], // Dedy Endra (Kurir)
            ['login' => '08115557089', 'password' => 'tikismd123', 'path' => '/owner/executive-dashboard'], // Bpk. Olly (Owner)
        ];

        foreach ($map as $entry) {
            $res = $this->post('/login', ['login' => $entry['login'], 'password' => $entry['password']]);
            $res->assertRedirect($entry['path']);
            $this->get($entry['path'])->assertOk();
            $this->post('/logout');
        }
    }

    public function test_self_attendance_is_disabled_and_hsm_portal_attendance_is_displayed(): void
    {
        $freelancer = User::where('role', User::ROLE_FREELANCER)->firstOrFail();
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();
        $office = Outlet::mainOffice();

        // 1. Percobaan presensi mandiri di kurir.tikismd.id ditolak karena sepenuhnya ditangani tikismd.id
        $this->actingAs($freelancer)
            ->postJson('/pwa/attendance/check-in', [
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'accuracy' => 8,
            ])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);

        // 2. Simulasikan kurir melakukan presensi di aplikasi absensi utama HSM (tikismd.id)
        $attId = $this->seedHsmCheckIn($freelancer, [
            'actual_check_in' => today()->toDateString().' 07:35:12',
            'distance_meter' => 6.4,
            'within_radius' => true,
        ]);

        // 3. Data jam masuk, radius kantor, dan selfie langsung tampil di PWA Kurir & Monitoring HRD
        $this->actingAs($freelancer)
            ->get('/pwa/attendance')
            ->assertOk()
            ->assertSee('07:35:12')
            ->assertSee('TIKI SR')
            ->assertSee('6.4 m')
            ->assertDontSee('Presensi Masuk Sekarang');

        $this->actingAs($hrd)
            ->get('/hrd/attendances')
            ->assertOk()
            ->assertSee($freelancer->name)
            ->assertSee('07:35:12')
            ->assertSee('Radius 25 m')
            ->assertSee("/media/hsm-attendance/{$attId}/in");
    }

    public function test_task_lifecycle_blocks_without_attendance_then_tracks_3_states_and_scores_kpi(): void
    {
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();
        $freelancer = User::where('role', User::ROLE_FREELANCER)->firstOrFail();
        $gerai = Outlet::where('code', 'GR-001')->firstOrFail();

        // HRD membuat tugas untuk kurir hari ini
        $this->actingAs($hrd)->post('/hrd/tasks', [
            'user_ids' => [$freelancer->id],
            'outlet_id' => $gerai->id,
            'title' => 'Audit Pengiriman Paket Gerai Ilir',
            'description' => 'Cek serah terima paket kurir',
            'task_type' => TaskAssignment::TYPE_FIXED,
            'assigned_date' => today()->toDateString(),
            'scheduled_start_time' => '09:00',
            'estimated_duration_minutes' => 30,
        ])->assertRedirect();

        $task = TaskAssignment::where('user_id', $freelancer->id)->firstOrFail();
        $this->assertSame('pending', $task->status);

        // Karyawan kurir mencoba mulai sebelum presensi di Portal HSM -> Ditolak
        $this->actingAs($freelancer)
            ->postJson("/pwa/tasks/{$task->id}/start", [
                'latitude' => $gerai->latitude,
                'longitude' => $gerai->longitude,
                'accuracy' => 10,
            ])
            ->assertStatus(422);

        // Kurir melakukan presensi masuk di aplikasi absensi HSM (tikismd.id)
        $this->seedHsmCheckIn($freelancer);

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
                'notes' => 'Pengecekan serah terima paket selesai.',
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
        $superadmin = User::where('role', User::ROLE_SUPERADMIN)->firstOrFail();
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();
        $owner = User::where('role', User::ROLE_OWNER)->firstOrFail();
        $freelancer = User::where('role', User::ROLE_FREELANCER)->firstOrFail();

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

        // Karyawan PWA pages
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
