<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\PerformanceLog;
use App\Models\Setting;
use App\Models\TaskAssignment;
use App\Models\User;
use Carbon\Carbon;

/**
 * Mesin KPI: Skor = (Wc × Selesai) + (Wt × Waktu) + (Wl × Lokasi) + (Wa × Kehadiran)
 * Bobot standar PRD 35/30/20/15, dapat diubah SuperAdmin di Pengaturan.
 */
class PerformanceScoringService
{
    public function calculateForUserDate(User $user, Carbon|string $date): ?PerformanceLog
    {
        $date = Carbon::parse($date)->startOfDay();

        $tasks = TaskAssignment::query()
            ->where('user_id', $user->id)
            ->whereDate('assigned_date', $date->toDateString())
            ->notCancelled()
            ->get();

        if ($tasks->isEmpty()) {
            PerformanceLog::where('user_id', $user->id)->whereDate('evaluation_date', $date->toDateString())->delete();

            return null;
        }

        $total = $tasks->count();
        $completed = $tasks->where('status', TaskAssignment::STATUS_COMPLETED);
        $completedCount = $completed->count();

        // 1. Penyelesaian
        $completionScore = $completedCount / $total * 100;

        // 2. Ketepatan waktu (rata-rata skor tugas yang selesai)
        $onTime = 0;
        $timeScores = $completed->map(function (TaskAssignment $t) use (&$onTime) {
            $deviation = (int) $t->deviationMinutes();
            if ($deviation <= 0) {
                $onTime++;
            }

            return self::taskTimeScore($deviation);
        });
        $timeScore = $timeScores->isEmpty() ? 0.0 : $timeScores->avg();

        // 3. Kepatuhan lokasi (tugas valid / total tugas)
        $validLocation = $tasks->where('is_location_valid', true)->count();
        $locationScore = $validLocation / $total * 100;

        // 4. Kedisiplinan presensi
        $attendance = Attendance::where('user_id', $user->id)->whereDate('date', $date->toDateString())->first();
        $attendanceScore = self::attendanceScore($attendance);

        [$wc, $wt, $wl, $wa] = $this->weights();
        $final = ($wc * $completionScore + $wt * $timeScore + $wl * $locationScore + $wa * $attendanceScore);
        $final = round(max(0, min(100, $final)), 2);

        $workMinutes = (int) $completed->sum('actual_duration_minutes');

        return PerformanceLog::updateOrCreate(
            ['user_id' => $user->id, 'evaluation_date' => $date->toDateString()],
            [
                'total_tasks' => $total,
                'completed_tasks' => $completedCount,
                'on_time_tasks' => $onTime,
                'valid_location_tasks' => $validLocation,
                'total_work_minutes' => $workMinutes,
                'completion_score' => round($completionScore, 2),
                'time_score' => round($timeScore, 2),
                'location_score' => round($locationScore, 2),
                'attendance_score' => round($attendanceScore, 2),
                'final_kpi_score' => $final,
                'grade' => self::gradeFor($final),
                'remarks' => $this->remarks($completedCount, $total, $onTime, $validLocation, $attendance),
            ]
        );
    }

    /** Hitung ulang seluruh freelancer aktif pada satu tanggal. Mengembalikan jumlah log yang dihasilkan. */
    public function calculateForDate(Carbon|string $date): int
    {
        $date = Carbon::parse($date);
        $count = 0;

        User::freelancers()->get()->each(function (User $user) use ($date, &$count) {
            if ($this->calculateForUserDate($user, $date)) {
                $count++;
            }
        });

        return $count;
    }

    public static function gradeFor(float $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 80 => 'B',
            $score >= 70 => 'C',
            default => 'D',
        };
    }

    /** Skor satu tugas berdasar deviasi durasi aktual terhadap SLA (menit). */
    public static function taskTimeScore(int $deviationMinutes): float
    {
        if ($deviationMinutes <= 0) {
            return 100.0;
        }
        if ($deviationMinutes <= 15) {
            return 85.0;
        }

        return max(0.0, 100 - 2.5 * $deviationMinutes);
    }

    public static function attendanceScore(?Attendance $attendance): float
    {
        if (! $attendance) {
            return 0.0;
        }
        if ($attendance->status === Attendance::STATUS_ON_TIME) {
            return 100.0;
        }

        return $attendance->late_minutes <= 15 ? 80.0 : 50.0;
    }

    /** @return array{0: float, 1: float, 2: float, 3: float} bobot (fraksi) yang dinormalisasi ke total 1. */
    public function weights(): array
    {
        $raw = [
            (float) Setting::get('weight_completion', 35),
            (float) Setting::get('weight_time', 30),
            (float) Setting::get('weight_location', 20),
            (float) Setting::get('weight_attendance', 15),
        ];
        $sum = array_sum($raw);
        if ($sum <= 0) {
            return [0.35, 0.30, 0.20, 0.15];
        }

        return array_map(fn ($w) => $w / $sum, $raw);
    }

    protected function remarks(int $completed, int $total, int $onTime, int $validLocation, ?Attendance $attendance): string
    {
        $parts = ["{$completed}/{$total} tugas selesai"];
        if ($completed > 0) {
            $parts[] = "{$onTime} tepat SLA";
        }
        $parts[] = "{$validLocation} tugas di lokasi valid";
        $parts[] = match (true) {
            ! $attendance => 'tidak ada presensi',
            $attendance->status === Attendance::STATUS_ON_TIME => 'presensi tepat waktu',
            default => "presensi terlambat {$attendance->late_minutes} menit",
        };

        return ucfirst(implode('; ', $parts)).'.';
    }
}
