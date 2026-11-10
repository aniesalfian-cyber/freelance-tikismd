<?php

namespace Database\Seeders;

use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\TaskTemplate;
use Illuminate\Database\Seeder;

class TaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $gerai = Outlet::where('code', 'GR-001')->first();
        if (! $gerai) {
            return;
        }

        $templates = [
            ['title' => 'Pengecekan Stok Awal Gerai', 'description' => 'Hitung & cocokkan stok awal hari dengan catatan sistem.', 'scheduled_start_time' => '09:00:00', 'estimated_duration_minutes' => 45],
            ['title' => 'Pembersihan Area Kerja & Display', 'description' => 'Bersihkan area kerja, rak, dan display produk.', 'scheduled_start_time' => '10:00:00', 'estimated_duration_minutes' => 30],
            ['title' => 'Rekap Harian & Pelaporan', 'description' => 'Susun rekap penjualan/aktivitas harian dan laporkan ke supervisor.', 'scheduled_start_time' => '15:30:00', 'estimated_duration_minutes' => 40],
        ];

        foreach ($templates as $t) {
            TaskTemplate::updateOrCreate(
                ['title' => $t['title']],
                $t + ['task_type' => TaskAssignment::TYPE_FIXED, 'default_outlet_id' => $gerai->id, 'default_user_id' => null, 'active_days' => null, 'is_active' => true]
            );
        }
    }
}
