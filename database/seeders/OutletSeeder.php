<?php

namespace Database\Seeders;

use App\Models\Outlet;
use Illuminate\Database\Seeder;

class OutletSeeder extends Seeder
{
    public function run(): void
    {
        $outlets = [
            ['code' => 'HSM-HQ', 'name' => 'Kantor Utama HSM Samarinda', 'address' => 'Jl. Gajah Mada No. 1, Pasar Pagi, Kec. Samarinda Kota, Kota Samarinda, Kalimantan Timur', 'latitude' => -0.50210600, 'longitude' => 117.15370900, 'radius_meters' => 50, 'is_main_office' => true],
            ['code' => 'GR-001', 'name' => 'Gerai Samarinda Ilir', 'address' => 'Jl. P. Hidayatullah, Pelabuhan, Kec. Samarinda Kota, Kota Samarinda', 'latitude' => -0.50785000, 'longitude' => 117.16340000, 'radius_meters' => 50, 'is_main_office' => false],
            ['code' => 'GR-002', 'name' => 'Gerai Samarinda Seberang', 'address' => 'Jl. Bung Tomo, Baqa, Kec. Samarinda Seberang, Kota Samarinda', 'latitude' => -0.51764000, 'longitude' => 117.13982000, 'radius_meters' => 50, 'is_main_office' => false],
            ['code' => 'GR-003', 'name' => 'Gerai Samarinda Ulu', 'address' => 'Jl. Ir. H. Juanda, Air Hitam, Kec. Samarinda Ulu, Kota Samarinda', 'latitude' => -0.47892000, 'longitude' => 117.14155000, 'radius_meters' => 50, 'is_main_office' => false],
        ];

        foreach ($outlets as $o) {
            Outlet::updateOrCreate(['code' => $o['code']], $o + ['is_active' => true]);
        }
    }
}
