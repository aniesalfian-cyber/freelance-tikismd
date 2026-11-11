<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $defaultPassword = Hash::make('123456');

        $users = [
            // SuperAdmin
            [
                'name' => 'Super Administrator',
                'email' => 'superadmin@hsm.id',
                'phone' => '081100000001',
                'nik' => 'SA-0001',
                'role' => User::ROLE_SUPERADMIN,
                'specialization' => 'IT & System Admin',
                'password' => $defaultPassword,
                'is_active' => true,
            ],

            // Admin HRD (4 Personel)
            [
                'name' => 'Arif',
                'email' => 'arif@hsm.id',
                'phone' => '085250815716',
                'nik' => 'HRD-0001',
                'role' => User::ROLE_HRD,
                'specialization' => 'Admin HRD / Koordinator',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Vhendy',
                'email' => 'vhendy@hsm.id',
                'phone' => '081346289989',
                'nik' => 'HRD-0002',
                'role' => User::ROLE_HRD,
                'specialization' => 'Admin HRD / Operasional',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Jeanny',
                'email' => 'jeanny@hsm.id',
                'phone' => '081217720557',
                'nik' => 'HRD-0003',
                'role' => User::ROLE_HRD,
                'specialization' => 'Admin HRD / Personalia',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Ahmad',
                'email' => 'ahmad@hsm.id',
                'phone' => '085390307018',
                'nik' => 'HRD-0004',
                'role' => User::ROLE_HRD,
                'specialization' => 'Admin HRD / Supervisor Lapangan',
                'password' => $defaultPassword,
                'is_active' => true,
            ],

            // Owner HSM (1 Personel)
            [
                'name' => 'Bpk. Olly',
                'email' => 'olly@hsm.id',
                'phone' => '08115557089',
                'nik' => 'OWN-0001',
                'role' => User::ROLE_OWNER,
                'specialization' => 'Direksi / Owner PT. HSM',
                'password' => $defaultPassword,
                'is_active' => true,
            ],

            // Karyawan Kurir Lapangan (17 Personel)
            [
                'name' => 'Dedy Endra',
                'email' => 'dedy.endra@hsm.id',
                'phone' => '081349497879',
                'nik' => 'KUR-0001',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R4 Kurier Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Hendra Saputra',
                'email' => 'hendra.saputra@hsm.id',
                'phone' => '085654207395',
                'nik' => 'KUR-0002',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R4 Gerai, Corporate & Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Ahmadi',
                'email' => 'ahmadi@hsm.id',
                'phone' => '085820421588',
                'nik' => 'KUR-0003',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R4 Gerai, Corporate & Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Hendra Wahyudi',
                'email' => 'hendra.wahyudi@hsm.id',
                'phone' => '081347457604',
                'nik' => 'KUR-0004',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Rinaldi Arsandi',
                'email' => 'rinaldi.arsandi@hsm.id',
                'phone' => '083895530302',
                'nik' => 'KUR-0005',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Agus Harianto Yusuf',
                'email' => 'agus.harianto@hsm.id',
                'phone' => '082152444602',
                'nik' => 'KUR-0006',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Muhammad Aditya Raya Saputra',
                'email' => 'aditya.saputra@hsm.id',
                'phone' => '081257472659',
                'nik' => 'KUR-0007',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Andra Pranata Hamid',
                'email' => 'andra.pranata@hsm.id',
                'phone' => '085163040104',
                'nik' => 'KUR-0008',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Junaidi',
                'email' => 'junaidi@hsm.id',
                'phone' => '085386781345',
                'nik' => 'KUR-0009',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Yusuf Taqwa',
                'email' => 'yusuf.taqwa@hsm.id',
                'phone' => '081549094581',
                'nik' => 'KUR-0010',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Farel Naraya',
                'email' => 'farel.naraya@hsm.id',
                'phone' => '081545673328',
                'nik' => 'KUR-0011',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Wahyudi',
                'email' => 'wahyudi@hsm.id',
                'phone' => '082245542542',
                'nik' => 'KUR-0012',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Rachman',
                'email' => 'rachman@hsm.id',
                'phone' => '082151829063',
                'nik' => 'KUR-0013',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Delivery',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Mardiansyah',
                'email' => 'mardiansyah@hsm.id',
                'phone' => '081347850510',
                'nik' => 'KUR-0014',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Pickup Corporate',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Rezaldi Hasbar',
                'email' => 'rezaldi.hasbar@hsm.id',
                'phone' => '085753386405',
                'nik' => 'KUR-0015',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Pickup Corporate',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Reggy Julio Pratama',
                'email' => 'reggy.pratama@hsm.id',
                'phone' => '08127779947',
                'nik' => 'KUR-0016',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Pickup Corporate',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
            [
                'name' => 'Farid Asrar',
                'email' => 'farid.asrar@hsm.id',
                'phone' => '082251693499',
                'nik' => 'KUR-0017',
                'role' => User::ROLE_FREELANCER,
                'specialization' => 'R2 Pickup Corporate',
                'password' => $defaultPassword,
                'is_active' => true,
            ],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(
                ['phone' => $data['phone']],
                $data
            );
        }
    }
}
