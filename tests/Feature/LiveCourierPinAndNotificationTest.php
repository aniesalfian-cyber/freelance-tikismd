<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LiveCourierPinAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_quick_login_is_removed_and_default_4_digit_pin_login_works(): void
    {
        // Pastikan halaman login tidak lagi menampilkan Quick Login dan memuat tombol Install PWA & Login PIN
        $loginPage = $this->get('/login');
        $loginPage->assertOk()
            ->assertDontSee('Pintasan Masuk Cepat Akun Real')
            ->assertSee('Login PIN (4 Digit)')
            ->assertSee('Install PWA');

        // Login menggunakan Nomor HP Kurir + PIN default 1234
        $res = $this->post('/login', [
            'login_mode' => 'pin',
            'login' => '081349497879',
            'pin' => '1234',
        ]);
        $res->assertRedirect('/pwa/workspace');
    }

    public function test_user_can_change_pin_password_and_profile_photo(): void
    {
        Storage::fake('public');
        $courier = User::where('phone', '081349497879')->firstOrFail();

        // 1. Ubah profil & upload foto
        $photo = UploadedFile::fake()->image('avatar.jpg', 200, 200);
        $this->actingAs($courier)
            ->put('/profile', [
                'name' => 'Dedy Endra Updated',
                'email' => $courier->email,
                'phone' => '081349497879',
                'avatar' => $photo,
            ])
            ->assertSessionHas('success');

        $courier->refresh();
        $this->assertSame('Dedy Endra Updated', $courier->name);
        $this->assertNotNull($courier->avatar);

        // 2. Ganti PIN dari 1234 menjadi 7788
        $this->actingAs($courier)
            ->put('/profile/pin', [
                'current_pin' => '1234',
                'pin' => '7788',
                'pin_confirmation' => '7788',
            ])
            ->assertSessionHas('success');

        // Logout lalu login menggunakan PIN baru 7788
        $this->post('/logout');
        $this->post('/login', [
            'login_mode' => 'pin',
            'login' => '081349497879',
            'pin' => '7788',
        ])->assertRedirect('/pwa/workspace');
    }

    public function test_working_courier_automatically_sends_live_gps_and_hrd_can_quick_assign_from_map(): void
    {
        $courier = User::where('role', User::ROLE_FREELANCER)->firstOrFail();
        $hrd = User::where('role', User::ROLE_HRD)->firstOrFail();
        $office = Outlet::mainOffice();

        // 1. Sebelum presensi masuk, heartbeat tidak menyimpan lokasi kerja
        $this->actingAs($courier)
            ->postJson('/pwa/heartbeat', [
                'latitude' => -0.502500,
                'longitude' => 117.154000,
                'accuracy' => 12,
            ])
            ->assertOk()
            ->assertJsonPath('is_working', false)
            ->assertJsonPath('location_saved', false);

        // 2. Kurir melakukan presensi masuk di Kantor Utama
        $this->actingAs($courier)
            ->postJson('/pwa/attendance/check-in', [
                'latitude' => $office->latitude,
                'longitude' => $office->longitude,
                'accuracy' => 10,
            ])
            ->assertOk();

        // 3. Saat berstatus masuk kerja, aplikasi kurir otomatis mengirim lokasi GPS terkini ke server
        $this->actingAs($courier)
            ->postJson('/pwa/heartbeat', [
                'latitude' => -0.505123,
                'longitude' => 117.158456,
                'accuracy' => 9,
            ])
            ->assertOk()
            ->assertJsonPath('is_working', true)
            ->assertJsonPath('location_saved', true);

        $courier->refresh();
        $this->assertEqualsWithDelta(-0.505123, $courier->last_latitude, 0.00001);
        $this->assertEqualsWithDelta(117.158456, $courier->last_longitude, 0.00001);

        // 4. Admin HRD memantau posisi kurir melalui endpoint Peta Sebaran Live
        $liveRes = $this->actingAs($hrd)
            ->getJson('/hrd/live-couriers')
            ->assertOk();

        $couriersData = collect($liveRes->json('couriers'));
        $tracked = $couriersData->firstWhere('id', $courier->id);
        $this->assertNotNull($tracked);
        $this->assertTrue($tracked['is_working']);
        $this->assertEqualsWithDelta(-0.505123, $tracked['lat'], 0.00001);

        // 5. Admin HRD mengklik titik kurir di peta dan memberikan Tugas Dadakan langsung dari Map
        $assignRes = $this->actingAs($hrd)
            ->postJson('/hrd/tasks/quick-assign', [
                'user_id' => $courier->id,
                'outlet_id' => $office->id,
                'title' => 'Pickup Dokumen Dadakan via Peta',
                'description' => 'Segera ambil paket dokumen di resepsionis',
                'estimated_duration_minutes' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(
            TaskAssignment::where('user_id', $courier->id)
                ->where('title', 'Pickup Dokumen Dadakan via Peta')
                ->exists()
        );

        // 6. Pastikan notifikasi & antrean Push Notifikasi diterima oleh kurir pada heartbeat berikutnya
        $hbAfter = $this->actingAs($courier)
            ->postJson('/pwa/heartbeat')
            ->assertOk();

        $this->assertGreaterThan(0, $hbAfter->json('unread_count'));
        $pushQueue = $hbAfter->json('push_queue');
        $this->assertNotEmpty($pushQueue);
        $this->assertStringContainsString('Pickup Dokumen Dadakan via Peta', $pushQueue[0]['title']);
    }
}
