<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceService
{
    public function __construct(protected HsmAttendanceBridgeService $bridge) {}

    public function today(User $user): ?Attendance
    {
        return $this->bridge->todayForUser($user);
    }

    public function todayStatus(User $user): ?Attendance
    {
        return $this->bridge->todayStatusForUser($user);
    }

    /**
     * @return Collection<int, Attendance>
     */
    public function history(User $user, int $limit = 15): Collection
    {
        return $this->bridge->historyForUser($user, $limit);
    }

    public function portalUrl(): string
    {
        return $this->bridge->portalUrl();
    }

    public function checkIn(User $user, float $lat, float $lng, ?float $accuracy): Attendance
    {
        throw new BusinessRuleException(
            'Pencatatan presensi masuk sepenuhnya dilakukan melalui aplikasi absensi utama HSM ('.$this->portalUrl().').'
        );
    }

    public function checkOut(User $user, float $lat, float $lng, ?float $accuracy): Attendance
    {
        throw new BusinessRuleException(
            'Pencatatan presensi pulang sepenuhnya dilakukan melalui aplikasi absensi utama HSM ('.$this->portalUrl().').'
        );
    }

    public function manualEntry(User $freelancer, Carbon $date, string $checkInTime, ?string $checkOutTime, string $notes, User $actor): Attendance
    {
        throw new BusinessRuleException(
            'Koreksi dan input presensi sepenuhnya dikelola melalui aplikasi absensi utama HSM ('.$this->portalUrl().').'
        );
    }
}
