<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Outlet;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\PerformanceScoringService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Data demo 14 hari terakhir (freelancer, tugas, presensi, KPI) untuk uji dashboard & laporan.
 * Jangan dijalankan di produksi.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([SettingSeeder::class, UserSeeder::class, OutletSeeder::class, TaskTemplateSeeder::class]);

        $hrd = User::where('role', User::ROLE_HRD)->first();
        $office = Outlet::mainOffice();
        $branches = Outlet::branches()->get();
        $scoring = app(PerformanceScoringService::class);

        $extra = [
            ['Ahmad Rizky', 'ahmad@hsm.id', 'FL-0002', 0.95],
            ['Budi Santoso', 'budi@hsm.id', 'FL-0003', 0.82],
            ['Citra Lestari', 'citra@hsm.id', 'FL-0004', 0.70],
            ['Dewi Anggraini', 'dewi@hsm.id', 'FL-0005', 0.55],
        ];
        $profiles = [];
        foreach ($extra as [$name, $email, $nik, $skill]) {
            $u = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'nik' => $nik, 'phone' => '0812'.random_int(10000000, 99999999),
                'password' => 'Password123!', 'role' => User::ROLE_FREELANCER, 'is_active' => true,
                'specialization' => 'Operasional Gerai', 'hourly_rate' => 25000,
            ]);
            $profiles[$u->id] = [$u, $skill];
        }
        $sample = User::where('email', 'freelancer@hsm.id')->first();
        $profiles[$sample->id] = [$sample, 0.78];

        mt_srand(2026);
        $titles = ['Pengecekan Stok Awal Gerai', 'Pembersihan Area Kerja & Display', 'Rekap Harian & Pelaporan', 'Audit Kebersihan Gerai', 'Penataan Display Produk'];

        for ($i = 13; $i >= 0; $i--) {
            $date = today()->subDays($i);
            if ($date->isSunday()) {
                continue;
            }

            foreach ($profiles as [$user, $skill]) {
                if (mt_rand(1, 100) > 92 && $i > 0) {
                    continue; // absen
                }

                // Presensi
                $late = mt_rand(0, 100) / 100 > $skill ? mt_rand(3, 40) : 0;
                $checkIn = $date->copy()->setTime(8, 0)->addMinutes($late ? $late : -mt_rand(1, 25));
                if ($i === 0 && $checkIn->isFuture()) {
                    $checkIn = now()->subMinutes(5);
                }
                Attendance::updateOrCreate(['user_id' => $user->id, 'date' => $date->toDateString()], [
                    'outlet_id' => $office->id,
                    'check_in_at' => $checkIn,
                    'check_in_latitude' => $office->latitude,
                    'check_in_longitude' => $office->longitude,
                    'check_in_distance_meters' => mt_rand(3, 40),
                    'check_in_accuracy' => mt_rand(5, 25),
                    'late_minutes' => $late,
                    'check_out_at' => ($i > 0) ? $date->copy()->setTime(17, mt_rand(0, 30)) : null,
                    'status' => $late ? 'terlambat' : 'hadir_tepat',
                ]);

                // Tugas
                $taskCount = mt_rand(3, 5);
                for ($n = 0; $n < $taskCount; $n++) {
                    $outlet = $branches->random();
                    $est = [30, 40, 45, 60][mt_rand(0, 3)];
                    $startHour = 9 + $n * 2;
                    $isFixed = $n < 3;
                    $task = TaskAssignment::create([
                        'task_number' => TaskAssignment::generateNumber($date),
                        'user_id' => $user->id,
                        'outlet_id' => $outlet->id,
                        'title' => $titles[$n % count($titles)],
                        'description' => 'Tugas demo operasional gerai.',
                        'task_type' => $isFixed ? 'pasti_harian' : 'tidak_pasti',
                        'assigned_date' => $date->toDateString(),
                        'scheduled_start_time' => sprintf('%02d:00:00', $startHour),
                        'estimated_duration_minutes' => $est,
                        'status' => 'pending',
                        'created_by' => $hrd->id,
                    ]);

                    $isToday = $i === 0;
                    $roll = mt_rand(1, 100) / 100;
                    if ($isToday && $n >= 2) {
                        if ($n === 2) {
                            $task->update(['status' => 'in_progress', 'started_at' => now()->subMinutes(mt_rand(5, 20)),
                                'start_latitude' => $outlet->latitude, 'start_longitude' => $outlet->longitude, 'start_distance_meters' => mt_rand(2, 30)]);
                        }
                        continue;
                    }
                    if ($roll > 0.96 && ! $isToday) {
                        continue; // tidak dikerjakan
                    }

                    $started = $date->copy()->setTime($startHour, mt_rand(0, 20));
                    $over = mt_rand(0, 100) / 100 > $skill ? mt_rand(5, 35) : -mt_rand(0, 10);
                    $actual = max(5, $est + $over);
                    $validLoc = mt_rand(0, 100) / 100 < (0.85 + $skill * 0.15);
                    $dist = $validLoc ? mt_rand(2, 40) : mt_rand(80, 400);
                    $task->update([
                        'status' => 'completed',
                        'started_at' => $started,
                        'start_latitude' => $outlet->latitude, 'start_longitude' => $outlet->longitude, 'start_distance_meters' => $dist,
                        'completed_at' => $started->copy()->addMinutes($actual),
                        'end_latitude' => $outlet->latitude, 'end_longitude' => $outlet->longitude, 'end_distance_meters' => $dist,
                        'actual_duration_minutes' => $actual,
                        'notes' => 'Pekerjaan selesai sesuai prosedur dan dilaporkan ke supervisor.',
                        'is_location_valid' => $validLoc,
                    ]);
                }

                $scoring->calculateForUserDate($user, $date);
            }
        }
    }
}
