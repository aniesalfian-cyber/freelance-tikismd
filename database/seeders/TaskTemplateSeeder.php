<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\TaskTemplate;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $office = Outlet::mainOffice() ?? Outlet::first();
        if (! $office) {
            return;
        }

        $templates = [
            // 1. DIVISI R4 KURIER DELIVERY (Mobil Box - Hanya Pengantaran)
            [
                'title' => 'Pengantaran Paket Mobil Box R4 - Rute Kota Samarinda',
                'description' => 'Pengantaran paket berukuran besar / kargo berat menggunakan mobil box ke alamat penerima & gerai transit.',
                'target_division' => User::DIVISION_R4_DELIVERY,
                'scheduled_start_time' => '09:00:00',
                'estimated_duration_minutes' => 180,
            ],

            // 2. DIVISI R4 GERAI, CORPORATE & DELIVERY (Mobil Box - Pickup & Delivery)
            [
                'title' => 'Shuttle & Pickup Gerai Cabang TIKISMD (Mobil Box R4)',
                'description' => 'Pengambilan (pickup) paket dari gerai-gerai cabang TIKISMD di Samarinda dan serah terima ke gudang operasional.',
                'target_division' => User::DIVISION_R4_GERAI_CORP,
                'scheduled_start_time' => '10:00:00',
                'estimated_duration_minutes' => 120,
            ],
            [
                'title' => 'Pickup & Pengantaran Kargo Corporate Rekanan (Mobil Box R4)',
                'description' => 'Pickup kargo dari perusahaan rekanan TIKISMD serta delivery paket corporate ke tujuan penyerahan.',
                'target_division' => User::DIVISION_R4_GERAI_CORP,
                'scheduled_start_time' => '14:00:00',
                'estimated_duration_minutes' => 120,
            ],

            // 3. DIVISI R2 DELIVERY (Sepeda Motor - Hanya Pengantaran)
            [
                'title' => 'Pengantaran Paket Kurir Motor R2 - Sesi Pagi',
                'description' => 'Pengantaran paket retail dan dokumen menggunakan sepeda motor ke alamat penerima di Samarinda (Sesi Pagi).',
                'target_division' => User::DIVISION_R2_DELIVERY,
                'scheduled_start_time' => '08:45:00',
                'estimated_duration_minutes' => 180,
            ],
            [
                'title' => 'Pengantaran Paket Kurir Motor R2 - Sesi Siang',
                'description' => 'Pengantaran paket kurir sepeda motor kloter siang ke area perumahan dan perkantoran.',
                'target_division' => User::DIVISION_R2_DELIVERY,
                'scheduled_start_time' => '13:30:00',
                'estimated_duration_minutes' => 180,
            ],

            // 4. DIVISI R2 PICKUP CORPORATE (Sepeda Motor - Hanya Pickup Perusahaan Mitra)
            [
                'title' => 'Pickup Paket Corporate Reguler R2 (Sesi Siang)',
                'description' => 'Pengambilan berkas dan paket dari instansi & kantor perusahaan mitra rekanan TIKISMD.',
                'target_division' => User::DIVISION_R2_PICKUP_CORP,
                'scheduled_start_time' => '13:00:00',
                'estimated_duration_minutes' => 120,
            ],
            [
                'title' => 'Pickup Sore Closing Corporate Mitra R2',
                'description' => 'Pengambilan berkas dan paket sore hari sebelum jam tutup kantor mitra rekanan.',
                'target_division' => User::DIVISION_R2_PICKUP_CORP,
                'scheduled_start_time' => '16:30:00',
                'estimated_duration_minutes' => 90,
            ],
        ];

        foreach ($templates as $t) {
            TaskTemplate::updateOrCreate(
                ['title' => $t['title']],
                $t + [
                    'task_type' => TaskAssignment::TYPE_FIXED,
                    'default_outlet_id' => $office->id,
                    'default_user_id' => null,
                    'active_days' => null,
                    'is_active' => true,
                ]
            );
        }
    }
}
