<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = [
            ['code' => 'HSM-HQ', 'name' => 'Kantor Utama HSM', 'address' => 'Jl. Gajah Mada No. 1, Samarinda, Kalimantan Timur', 'latitude' => -0.50220000, 'longitude' => 117.15360000, 'radius_meters' => 50, 'is_main_office' => true],
            ['code' => 'GR-001', 'name' => 'Gerai Samarinda Ilir', 'address' => 'Jl. Pangeran Antasari, Samarinda Ilir', 'latitude' => -0.51900000, 'longitude' => 117.14800000, 'radius_meters' => 50, 'is_main_office' => false],
            ['code' => 'GR-002', 'name' => 'Gerai Samarinda Seberang', 'address' => 'Jl. D.I. Panjaitan, Samarinda Seberang', 'latitude' => -0.50350000, 'longitude' => 117.17500000, 'radius_meters' => 50, 'is_main_office' => false],
            ['code' => 'GR-003', 'name' => 'Gerai Samarinda Ulu', 'address' => 'Jl. Juanda, Samarinda Ulu', 'latitude' => -0.46900000, 'longitude' => 117.13250000, 'radius_meters' => 50, 'is_main_office' => false],
        ];

        foreach ($outlets as $o) {
            Outlet::updateOrCreate(['code' => $o['code']], $o + ['is_active' => true]);
        }
    }
}
