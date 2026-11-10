<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['name' => 'Super Administrator', 'email' => 'superadmin@hsm.id', 'role' => User::ROLE_SUPERADMIN, 'phone' => '081200000001', 'nik' => 'SA-0001'],
            ['name' => 'Admin HRD HSM', 'email' => 'hrd@hsm.id', 'role' => User::ROLE_HRD, 'phone' => '081200000002', 'nik' => 'HRD-0001'],
            ['name' => 'Freelancer Contoh', 'email' => 'freelancer@hsm.id', 'role' => User::ROLE_FREELANCER, 'phone' => '081200000003', 'nik' => 'FL-0001', 'specialization' => 'Operasional Gerai'],
            ['name' => 'Owner HSM', 'email' => 'owner@hsm.id', 'role' => User::ROLE_OWNER, 'phone' => '081200000004', 'nik' => 'OWN-0001'],
        ];

        foreach ($accounts as $account) {
            // Password bawaan untuk lingkungan awal — WAJIB diganti setelah login pertama.
            User::updateOrCreate(['email' => $account['email']], $account + ['password' => 'Password123!', 'is_active' => true]);
        }
    }
}
