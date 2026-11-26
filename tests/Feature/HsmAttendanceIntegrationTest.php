<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HsmAttendanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_couriers_map_by_normalized_phone_and_fallback_name_and_exclude_leave_rows_from_working(): void
    {
        $couriers = User::freelancers()->active()->orderBy('id')->take(3)->get()->values();
        $c1 = $couriers[0];
        $c2 = $couriers[1];
        $c3 = $couriers[2];
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();

        // Kurir 1: nomor HP format +62 di database HSM -> tetap cocok otomatis
        $plus62Phone = '+62'.substr(preg_replace('/\D+/', '', $c1->phone), 1);
        $att1 = $this->seedHsmCheckIn($c1, [
            'employee' => ['phone' => $plus62Phone],
            'actual_check_in' => today()->toDateString().' 07:20:00',
            'distance_meter' => 5.5,
        ]);
        $this->seedHsmCheckOut($att1, [
            'actual_check_out' => today()->toDateString().' 17:02:00',
            'distance_meter' => 8.2,
        ]);

        // Kurir 2: nomor HP berbeda 1 digit namun nama lengkap identik (kasus nyata lapangan)
        $this->seedHsmCheckIn($c2, [
            'employee' => [
                'phone' => '089999999999',
                'full_name' => strtoupper($c2->name).' (alias)',
            ],
            'actual_check_in' => today()->toDateString().' 08:12:00',
            'late_minutes' => 12,
            'check_in_status' => 'LATE',
            'attendance_status' => 'LATE',
            'distance_meter' => 14.0,
        ]);

        // Kurir 3: berstatus CUTI (actual_check_in = NULL) -> tidak boleh dihitung kurir masuk kerja
        $this->seedHsmLeave($c3, 'LEAVE', 'Cuti: Cuti Tahunan');

        $live = $this->actingAs($hrd)->getJson('/hrd/live-couriers')->assertOk();
        $list = collect($live->json('couriers'))->keyBy('id');

        $this->assertTrue($list[$c1->id]['is_working']);
        $this->assertTrue($list[$c1->id]['is_checked_out']);
        $this->assertSame('07:20', $list[$c1->id]['check_in_time']);
        $this->assertSame('17:02', $list[$c1->id]['check_out_time']);
        $this->assertSame('TIKI SR', $list[$c1->id]['attendance_office']);
        $this->assertStringContainsString('5.5 m / Radius 25 m', $list[$c1->id]['attendance_radius_summary']);

        $this->assertTrue($list[$c2->id]['is_working']);
        $this->assertFalse($list[$c2->id]['is_checked_out']);
        $this->assertSame('08:12', $list[$c2->id]['check_in_time']);

        $this->assertFalse($list[$c3->id]['is_working']);
        $this->assertSame('Cuti', $list[$c3->id]['leave_status']);

        // Halaman monitoring presensi HRD menampilkan jam masuk, pulang, radius, selfie, serta panel Cuti
        $this->actingAs($hrd)
            ->get('/hrd/attendances')
            ->assertOk()
            ->assertSee($c1->name)
            ->assertSee('07:20:00')
            ->assertSee('17:02:00')
            ->assertSee('5.5 m')
            ->assertSee('Cuti: Cuti Tahunan');
    }

    public function test_selfie_photo_endpoint_enforces_role_and_ownership_authorization(): void
    {
        $couriers = User::freelancers()->active()->orderBy('id')->take(2)->get()->values();
        $ownerCourier = $couriers[0];
        $otherCourier = $couriers[1];
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();

        $attId = $this->seedHsmCheckIn($ownerCourier);
        $this->seedHsmCheckOut($attId);

        // Kurir pemilik dapat membuka foto selfie masuk & pulang miliknya
        $this->actingAs($ownerCourier)
            ->get("/media/hsm-attendance/{$attId}/in")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        $this->actingAs($ownerCourier)
            ->get("/media/hsm-attendance/{$attId}/out")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');

        // Kurir lain ditolak (403)
        $this->actingAs($otherCourier)
            ->get("/media/hsm-attendance/{$attId}/in")
            ->assertForbidden();

        // HRD dapat memverifikasi foto selfie kurir
        $this->actingAs($hrd)
            ->get("/media/hsm-attendance/{$attId}/in")
            ->assertOk();

        // Manual attendance POST di kurir.tikismd.id ditolak dengan redirect error
        $this->actingAs($hrd)
            ->post('/hrd/attendances', [
                'user_id' => $otherCourier->id,
                'date' => today()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('error');
    }
}
