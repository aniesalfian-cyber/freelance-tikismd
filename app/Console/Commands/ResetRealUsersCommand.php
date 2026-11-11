<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Attendance;
use App\Models\PerformanceLog;
use App\Models\SystemBackup;
use App\Models\TaskAssignment;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetRealUsersCommand extends Command
{
    protected $signature = 'hsm:reset-real-data {--force : Force execute without confirmation}';
    protected $description = 'Hapus seluruh data dummy dan transaksi, lalu input data real personel PT. HSM';

    public function handle(): int
    {
        $this->warn('Membersihkan data dummy dan transaksi...');

        Schema::disableForeignKeyConstraints();

        // 1. Hapus transaksi dummy
        Attendance::truncate();
        TaskAssignment::truncate();
        PerformanceLog::truncate();
        ActivityLog::truncate();
        SystemBackup::truncate();
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->truncate();
        }

        // 2. Hapus seluruh user lama
        User::truncate();

        Schema::enableForeignKeyConstraints();

        $this->info('Seluruh data dummy dan transaksi berhasil dibersihkan.');

        // 3. Masukkan data real user
        $this->info('Memasukkan data real personel PT. HSM...');
        (new UserSeeder())->run();

        $totalUsers = User::count();
        $kurirCount = User::where('role', User::ROLE_FREELANCER)->count();
        $hrdCount = User::where('role', User::ROLE_HRD)->count();
        $ownerCount = User::where('role', User::ROLE_OWNER)->count();
        $saCount = User::where('role', User::ROLE_SUPERADMIN)->count();

        $this->table(
            ['Kategori', 'Jumlah Akun', 'Password Default', 'Metode Login'],
            [
                ['SuperAdmin', $saCount, '123456', 'Nomor HP (081100000001) / Email'],
                ['Admin HRD', $hrdCount, '123456', 'Nomor HP (Arif, Vhendy, Jeanny, Ahmad)'],
                ['Owner HSM', $ownerCount, '123456', 'Nomor HP (08115557089 - Bpk. Olly)'],
                ['Kurir Lapangan', $kurirCount, '123456', 'Nomor HP (17 Kurir Delivery/Pickup)'],
                ['TOTAL USER REAL', $totalUsers, '123456', 'No. HP masing-masing personel'],
            ]
        );

        $this->info('Selesai! Sistem kini bersih dari data dummy dan siap operasional.');
        return Command::SUCCESS;
    }
}
