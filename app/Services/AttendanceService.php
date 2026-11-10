<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\Outlet;
use App\Models\Setting;
use App\Models\User;
use App\Support\ActivityLogger;
use Carbon\Carbon;

class AttendanceService
{
    public function __construct(protected GeofencingService $geo) {}

    public function today(User $user): ?Attendance
    {
        return Attendance::where('user_id', $user->id)->whereDate('date', today())->first();
    }

    /**
     * Presensi masuk di Kantor Utama dengan validasi geofencing server-side.
     */
    public function checkIn(User $user, float $lat, float $lng, ?float $accuracy): Attendance
    {
        if ($this->today($user)) {
            throw new BusinessRuleException('Anda sudah melakukan presensi masuk hari ini.');
        }

        $office = Outlet::mainOffice();
        if (! $office) {
            throw new BusinessRuleException('Kantor Utama belum ditetapkan oleh Admin HRD. Hubungi HRD.');
        }

        [$accuracyOk, $accuracyMessage] = $this->geo->validateHardwareAccuracy($accuracy);
        if (! $accuracyOk) {
            throw new BusinessRuleException($accuracyMessage);
        }

        $distance = $this->geo->calculateDistance($lat, $lng, $office->latitude, $office->longitude);
        if ($distance > $office->radius_meters) {
            $over = (int) round($distance - $office->radius_meters);
            throw new BusinessRuleException(
                sprintf('Presensi gagal: Anda berada %d m dari %s (di luar radius %d m, selisih %d m). Silakan mendekat.',
                    round($distance), $office->name, $office->radius_meters, $over),
                ['distance' => (int) round($distance), 'radius' => $office->radius_meters]
            );
        }

        $now = now();
        [$status, $late] = $this->resolveStatus($now);

        $attendance = Attendance::create([
            'user_id' => $user->id,
            'outlet_id' => $office->id,
            'date' => $now->toDateString(),
            'check_in_at' => $now,
            'check_in_latitude' => $lat,
            'check_in_longitude' => $lng,
            'check_in_distance_meters' => (int) round($distance),
            'check_in_accuracy' => $accuracy,
            'late_minutes' => $late,
            'status' => $status,
        ]);

        ActivityLogger::log('attendance.check_in', "Presensi masuk {$user->name} ({$status}, jarak ".round($distance).' m)', $attendance, $user->id);

        return $attendance;
    }

    public function checkOut(User $user, float $lat, float $lng, ?float $accuracy): Attendance
    {
        $attendance = $this->today($user);
        if (! $attendance) {
            throw new BusinessRuleException('Anda belum melakukan presensi masuk hari ini.');
        }
        if ($attendance->check_out_at) {
            throw new BusinessRuleException('Anda sudah melakukan presensi pulang hari ini.');
        }

        [$accuracyOk, $accuracyMessage] = $this->geo->validateHardwareAccuracy($accuracy);
        if (! $accuracyOk) {
            throw new BusinessRuleException($accuracyMessage);
        }

        $office = $attendance->outlet ?? Outlet::mainOffice();
        $distance = $this->geo->calculateDistance($lat, $lng, $office->latitude, $office->longitude);
        if ($distance > $office->radius_meters) {
            throw new BusinessRuleException(
                sprintf('Presensi pulang gagal: Anda berada %d m dari %s (radius %d m). Kembali ke kantor utama.',
                    round($distance), $office->name, $office->radius_meters)
            );
        }

        $attendance->update([
            'check_out_at' => now(),
            'check_out_latitude' => $lat,
            'check_out_longitude' => $lng,
            'check_out_distance_meters' => (int) round($distance),
        ]);

        ActivityLogger::log('attendance.check_out', "Presensi pulang {$user->name}", $attendance, $user->id);

        return $attendance->fresh();
    }

    /**
     * Input presensi manual oleh HRD (validasi/bypass untuk kendala GPS atau perangkat).
     */
    public function manualEntry(User $freelancer, Carbon $date, string $checkInTime, ?string $checkOutTime, string $notes, User $actor): Attendance
    {
        if (Attendance::where('user_id', $freelancer->id)->whereDate('date', $date)->exists()) {
            throw new BusinessRuleException('Karyawan ini sudah memiliki presensi pada tanggal tersebut.');
        }

        $office = Outlet::mainOffice();
        if (! $office) {
            throw new BusinessRuleException('Kantor Utama belum ditetapkan.');
        }

        $checkIn = $date->copy()->setTimeFromTimeString($checkInTime);
        $checkOut = $checkOutTime ? $date->copy()->setTimeFromTimeString($checkOutTime) : null;
        if ($checkOut && $checkOut->lessThanOrEqualTo($checkIn)) {
            throw new BusinessRuleException('Jam pulang harus setelah jam masuk.');
        }

        [$status, $late] = $this->resolveStatus($checkIn);

        $attendance = Attendance::create([
            'user_id' => $freelancer->id,
            'outlet_id' => $office->id,
            'date' => $date->toDateString(),
            'check_in_at' => $checkIn,
            'check_in_latitude' => $office->latitude,
            'check_in_longitude' => $office->longitude,
            'check_in_distance_meters' => 0,
            'late_minutes' => $late,
            'check_out_at' => $checkOut,
            'status' => $status,
            'notes' => 'Input manual HRD: '.$notes,
        ]);

        ActivityLogger::log('attendance.manual', "Presensi manual {$freelancer->name} tanggal {$date->toDateString()} oleh {$actor->name}", $attendance, $actor->id);

        return $attendance;
    }

    /** @return array{0: string, 1: int} [status, menit terlambat] */
    public function resolveStatus(Carbon $checkIn): array
    {
        $start = $checkIn->copy()->setTimeFromTimeString((string) Setting::get('work_start_time', '08:00'));

        if ($checkIn->lessThanOrEqualTo($start)) {
            return [Attendance::STATUS_ON_TIME, 0];
        }

        return [Attendance::STATUS_LATE, (int) $start->diffInMinutes($checkIn)];
    }
}
