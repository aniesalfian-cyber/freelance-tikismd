<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeder dasar (aman untuk produksi awal). Data demo: `php artisan db:seed --class=DemoDataSeeder`.
     */
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            UserSeeder::class,
            OutletSeeder::class,
            TaskTemplateSeeder::class,
        ]);
    }
}
