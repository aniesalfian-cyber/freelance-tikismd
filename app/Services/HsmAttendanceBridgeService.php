<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only bridge to the main HSM attendance application (tikismd.id).
 * Never mutates tables on the `hsm_attendance` connection.
 */
class HsmAttendanceBridgeService
{
    protected ?bool $available = null;

    /** @var array{courier_to_emp: array<int, object>, emp_to_courier: array<int, User>}|null */
    protected ?array $mappingCache = null;

    /** @var array<string, array{present: Collection<int, Attendance>, non_working: Collection<int, Attendance>}> */
    protected array $dateCache = [];

    public function connectionName(): string
    {
        return (string) config('services.hsm_attendance.connection', 'hsm_attendance');
    }

    public function portalUrl(): string
    {
        return (string) config('services.hsm_attendance.portal_url', 'https://tikismd.id');
    }

    public function resetCache(): void
    {
        $this->available = null;
        $this->mappingCache = null;
        $this->dateCache = [];
    }

    public function isAvailable(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (! config('services.hsm_attendance.enabled', true)) {
            return $this->available = false;
        }

        $connName = $this->connectionName();
        $dbName = (string) config("database.connections.{$connName}.database", '');
        if ($dbName === '') {
            return $this->available = false;
        }

        try {
            $schema = Schema::connection($connName);

            return $this->available = ($schema->hasTable('employees') && $schema->hasTable('attendances'));
        } catch (Throwable) {
            return $this->available = false;
        }
    }

    /**
     * Today's check-in attendance for a courier (null if not checked in yet).
     */
    public function todayForUser(User $user): ?Attendance
    {
        return $this->forUserOnDate($user, today(), onlyCheckedIn: true);
    }

    /**
     * Today's attendance or non-working status (e.g. Cuti/Izin/Sakit) for a courier.
     */
    public function todayStatusForUser(User $user): ?Attendance
    {
        return $this->forUserOnDate($user, today(), onlyCheckedIn: false);
    }

    public function forUserOnDate(User $user, Carbon|string $date, bool $onlyCheckedIn = true): ?Attendance
    {
        $dateKey = Carbon::parse($date)->toDateString();

        if (! $this->isAvailable()) {
            $local = Attendance::where('user_id', $user->id)->whereDate('date', $dateKey)->first();

            return ($onlyCheckedIn && $local && ! $local->hasCheckedIn()) ? null : $local;
        }

        $day = $this->forDate($dateKey);
        if ($day['present']->has($user->id)) {
            return $day['present']->get($user->id);
        }

        if (! $onlyCheckedIn && $day['non_working']->has($user->id)) {
            return $day['non_working']->get($user->id);
        }

        return null;
    }

    /**
     * Load all courier attendances on a single date, partitioned into:
     * - `present`: keyed by courier `user_id` where `actual_check_in IS NOT NULL`
     * - `non_working`: keyed by courier `user_id` for leave/permission/sick/off rows without check-in
     *
     * @return array{present: Collection<int, Attendance>, non_working: Collection<int, Attendance>}
     */
    public function forDate(Carbon|string $date): array
    {
        $dateKey = Carbon::parse($date)->toDateString();
        if (isset($this->dateCache[$dateKey])) {
            return $this->dateCache[$dateKey];
        }

        if (! $this->isAvailable()) {
            $local = Attendance::with(['user', 'outlet'])
                ->whereDate('date', $dateKey)
                ->orderBy('check_in_at')
                ->get();

            return $this->dateCache[$dateKey] = [
                'present' => $local->filter(fn (Attendance $a) => $a->hasCheckedIn())->keyBy('user_id'),
                'non_working' => $local->reject(fn (Attendance $a) => $a->hasCheckedIn())->keyBy('user_id'),
            ];
        }

        $map = $this->resolveCourierMapping();
        $empIds = array_keys($map['emp_to_courier']);
        if ($empIds === []) {
            return $this->dateCache[$dateKey] = ['present' => collect(), 'non_working' => collect()];
        }

        $rows = $this->baseAttendanceQuery()
            ->whereIn('a.employee_id', $empIds)
            ->where('a.work_date', $dateKey)
            ->orderByRaw('CASE WHEN a.actual_check_in IS NULL THEN 1 ELSE 0 END ASC')
            ->orderBy('a.actual_check_in')
            ->orderByDesc('a.id')
            ->get();

        $hydrated = $this->hydrateRows($rows, $map['emp_to_courier']);

        $present = collect();
        $nonWorking = collect();

        foreach ($hydrated as $att) {
            if ($att->hasCheckedIn()) {
                if (! $present->has($att->user_id)) {
                    $present->put($att->user_id, $att);
                }
            } elseif ($att->isNonWorkingLeave() && ! $present->has($att->user_id) && ! $nonWorking->has($att->user_id)) {
                $nonWorking->put($att->user_id, $att);
            }
        }

        return $this->dateCache[$dateKey] = [
            'present' => $present,
            'non_working' => $nonWorking,
        ];
    }

    /**
     * Load check-in attendances across a date range (for Reports & Analytics).
     *
     * @return Collection<int, Attendance>
     */
    public function forDateRange(Carbon|string $from, Carbon|string $to, ?int $userId = null): Collection
    {
        $fromKey = Carbon::parse($from)->toDateString();
        $toKey = Carbon::parse($to)->toDateString();

        if (! $this->isAvailable()) {
            return Attendance::query()
                ->with(['user:id,name,nik,phone', 'outlet:id,name,radius_meters'])
                ->whereBetween('date', [$fromKey, $toKey])
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->orderBy('date')
                ->orderBy('check_in_at')
                ->get();
        }

        $map = $this->resolveCourierMapping(includeInactive: true);
        if ($userId !== null) {
            $emp = $map['courier_to_emp'][$userId] ?? null;
            if (! $emp) {
                return collect();
            }
            $empIds = [(int) $emp->id];
        } else {
            $empIds = array_keys($map['emp_to_courier']);
        }

        if ($empIds === []) {
            return collect();
        }

        $rows = $this->baseAttendanceQuery()
            ->whereIn('a.employee_id', $empIds)
            ->whereBetween('a.work_date', [$fromKey, $toKey])
            ->whereNotNull('a.actual_check_in')
            ->orderBy('a.work_date')
            ->orderBy('a.actual_check_in')
            ->get();

        return $this->hydrateRows($rows, $map['emp_to_courier']);
    }

    /**
     * Load recent attendance history for one courier (including past check-ins and approved leaves up to today).
     *
     * @return Collection<int, Attendance>
     */
    public function historyForUser(User $user, int $limit = 15): Collection
    {
        if (! $this->isAvailable()) {
            return Attendance::with('outlet')
                ->where('user_id', $user->id)
                ->latest('date')
                ->limit($limit)
                ->get();
        }

        $map = $this->resolveCourierMapping(includeInactive: true);
        $emp = $map['courier_to_emp'][$user->id] ?? null;
        if (! $emp) {
            return collect();
        }

        $rows = $this->baseAttendanceQuery()
            ->where('a.employee_id', (int) $emp->id)
            ->where('a.work_date', '<=', today()->toDateString())
            ->where(function ($q) {
                $q->whereNotNull('a.actual_check_in')
                    ->orWhereIn('a.attendance_status', ['LEAVE', 'PERMISSION', 'SICK', 'OFF']);
            })
            ->orderByDesc('a.work_date')
            ->orderByDesc('a.actual_check_in')
            ->limit($limit)
            ->get();

        return $this->hydrateRows($rows, [(int) $emp->id => $user]);
    }

    /**
     * Resolve and authorize an attendance selfie photo for streaming.
     *
     * @return array{file_path: string, mime_type: string}|null
     */
    public function findAuthorizedPhoto(int $hsmAttendanceId, string $type, User $viewer): ?array
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $photoType = strtoupper($type) === 'OUT' ? 'CHECK_OUT' : 'CHECK_IN';
        $db = $this->db();

        $att = $db->table('attendances')
            ->select(['id', 'employee_id'])
            ->where('id', $hsmAttendanceId)
            ->first();

        if (! $att) {
            return null;
        }

        if ($viewer->isFreelancer()) {
            $map = $this->resolveCourierMapping(includeInactive: true);
            $viewerEmp = $map['courier_to_emp'][$viewer->id] ?? null;
            if (! $viewerEmp || (int) $viewerEmp->id !== (int) $att->employee_id) {
                abort(403, 'Anda tidak memiliki akses ke foto presensi ini.');
            }
        }

        $photo = $db->table('attendance_photos')
            ->select(['file_path', 'mime_type'])
            ->where('attendance_id', $hsmAttendanceId)
            ->where('type', $photoType)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();

        if (! $photo || empty($photo->file_path)) {
            return null;
        }

        return [
            'file_path' => (string) $photo->file_path,
            'mime_type' => ! empty($photo->mime_type) ? (string) $photo->mime_type : 'image/jpeg',
        ];
    }

    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (str_starts_with($digits, '62')) {
            $digits = '0'.substr($digits, 2);
        } elseif ($digits !== '' && str_starts_with($digits, '8')) {
            $digits = '0'.$digits;
        }

        return $digits;
    }

    public static function normalizeName(?string $name): string
    {
        $clean = preg_replace('/\(.*?\)/', '', strtolower(trim((string) $name))) ?? '';

        return trim(preg_replace('/\s+/', ' ', $clean) ?? '');
    }

    protected function db(): ConnectionInterface
    {
        return DB::connection($this->connectionName());
    }

    protected function baseAttendanceQuery()
    {
        return $this->db()->table('attendances as a')
            ->leftJoin('employees as e', 'e.id', '=', 'a.employee_id')
            ->leftJoin('work_locations as wl', 'wl.id', '=', 'a.work_location_id')
            ->leftJoin('shifts as s', 's.id', '=', 'a.shift_id')
            ->select([
                'a.id as hsm_attendance_id',
                'a.employee_id',
                'a.work_date',
                'a.scheduled_check_in',
                'a.scheduled_check_out',
                'a.actual_check_in',
                'a.actual_check_out',
                'a.check_in_status',
                'a.check_out_status',
                'a.late_minutes',
                'a.is_late_excused',
                'a.attendance_status',
                'a.validation_status',
                'a.validation_note',
                'e.nik as hsm_nik',
                'e.full_name as hsm_full_name',
                'wl.name as location_name',
                'wl.address as location_address',
                'wl.latitude as office_latitude',
                'wl.longitude as office_longitude',
                'wl.radius_meter as office_radius_meters',
                's.name as shift_name',
                's.start_time as shift_start_time',
                's.end_time as shift_end_time',
            ]);
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<int, User>  $empToCourier
     * @return Collection<int, Attendance>
     */
    protected function hydrateRows(Collection $rows, array $empToCourier): Collection
    {
        if ($rows->isEmpty()) {
            return collect();
        }

        $attIds = $rows->pluck('hsm_attendance_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $db = $this->db();

        $locationsByAtt = [];
        if (Schema::connection($this->connectionName())->hasTable('attendance_locations')) {
            $locRows = $db->table('attendance_locations')
                ->select([
                    'id', 'attendance_id', 'type', 'latitude', 'longitude',
                    'accuracy_meter', 'distance_meter', 'within_radius', 'integrity_status', 'captured_at',
                ])
                ->whereIn('attendance_id', $attIds)
                ->orderBy('captured_at')
                ->orderBy('id')
                ->get();

            foreach ($locRows as $loc) {
                $locationsByAtt[(int) $loc->attendance_id][(string) $loc->type] = $loc;
            }
        }

        $photosByAtt = [];
        if (Schema::connection($this->connectionName())->hasTable('attendance_photos')) {
            $photoRows = $db->table('attendance_photos')
                ->select(['id', 'attendance_id', 'type', 'file_path', 'mime_type', 'captured_at'])
                ->whereIn('attendance_id', $attIds)
                ->orderBy('captured_at')
                ->orderBy('id')
                ->get();

            foreach ($photoRows as $ph) {
                $photosByAtt[(int) $ph->attendance_id][(string) $ph->type] = $ph;
            }
        }

        $tz = config('app.timezone', 'Asia/Makassar');
        $result = collect();

        foreach ($rows as $row) {
            $empId = (int) $row->employee_id;
            $courier = $empToCourier[$empId] ?? null;
            if (! $courier) {
                continue;
            }

            $attId = (int) $row->hsm_attendance_id;
            $inLoc = $locationsByAtt[$attId]['CHECK_IN'] ?? null;
            $outLoc = $locationsByAtt[$attId]['CHECK_OUT'] ?? null;
            $inPhoto = $photosByAtt[$attId]['CHECK_IN'] ?? null;
            $outPhoto = $photosByAtt[$attId]['CHECK_OUT'] ?? null;

            $status = $this->mapStatus($row, $inLoc);
            $lateMinutes = (! empty($row->is_late_excused)) ? 0 : (int) ($row->late_minutes ?? 0);

            $att = (new Attendance)->forceFill([
                'id' => $attId,
                'hsm_attendance_id' => $attId,
                'hsm_employee_id' => $empId,
                'hsm_nik' => $row->hsm_nik ?? null,
                'user_id' => $courier->id,
                'outlet_id' => null,
                'date' => Carbon::parse((string) $row->work_date, $tz)->startOfDay(),
                'scheduled_check_in' => $row->scheduled_check_in ? Carbon::parse((string) $row->scheduled_check_in, $tz) : null,
                'scheduled_check_out' => $row->scheduled_check_out ? Carbon::parse((string) $row->scheduled_check_out, $tz) : null,
                'check_in_at' => $row->actual_check_in ? Carbon::parse((string) $row->actual_check_in, $tz) : null,
                'check_out_at' => $row->actual_check_out ? Carbon::parse((string) $row->actual_check_out, $tz) : null,
                'check_in_latitude' => $inLoc && $inLoc->latitude !== null ? (float) $inLoc->latitude : ($row->office_latitude !== null ? (float) $row->office_latitude : null),
                'check_in_longitude' => $inLoc && $inLoc->longitude !== null ? (float) $inLoc->longitude : ($row->office_longitude !== null ? (float) $row->office_longitude : null),
                'check_in_distance_meters' => $inLoc && $inLoc->distance_meter !== null ? round((float) $inLoc->distance_meter, 1) : null,
                'check_in_accuracy' => $inLoc && $inLoc->accuracy_meter !== null ? round((float) $inLoc->accuracy_meter, 1) : null,
                'check_in_within_radius' => $inLoc ? (bool) $inLoc->within_radius : null,
                'check_out_latitude' => $outLoc && $outLoc->latitude !== null ? (float) $outLoc->latitude : null,
                'check_out_longitude' => $outLoc && $outLoc->longitude !== null ? (float) $outLoc->longitude : null,
                'check_out_distance_meters' => $outLoc && $outLoc->distance_meter !== null ? round((float) $outLoc->distance_meter, 1) : null,
                'check_out_accuracy' => $outLoc && $outLoc->accuracy_meter !== null ? round((float) $outLoc->accuracy_meter, 1) : null,
                'check_out_within_radius' => $outLoc ? (bool) $outLoc->within_radius : null,
                'late_minutes' => $lateMinutes,
                'status' => $status,
                'attendance_status_raw' => $row->attendance_status ?? null,
                'validation_status' => $row->validation_status ?? 'VALID',
                'notes' => $row->validation_note ?: null,
                'location_name' => $row->location_name ?? 'Kantor Utama HSM',
                'location_address' => $row->location_address ?? null,
                'office_latitude' => $row->office_latitude !== null ? (float) $row->office_latitude : null,
                'office_longitude' => $row->office_longitude !== null ? (float) $row->office_longitude : null,
                'office_radius_meters' => $row->office_radius_meters !== null ? (int) $row->office_radius_meters : 25,
                'shift_name' => $row->shift_name ?? null,
                'shift_start_time' => $row->shift_start_time ? substr((string) $row->shift_start_time, 0, 5) : null,
                'shift_end_time' => $row->shift_end_time ? substr((string) $row->shift_end_time, 0, 5) : null,
                'check_in_photo_path' => $inPhoto->file_path ?? null,
                'check_out_photo_path' => $outPhoto->file_path ?? null,
            ]);

            $att->exists = true;
            $att->setRelation('user', $courier);

            $result->push($att);
        }

        return $result;
    }

    protected function mapStatus(object $row, ?object $inLoc): string
    {
        if (! empty($row->actual_check_in)) {
            if (($row->validation_status ?? '') === 'REJECTED') {
                return Attendance::STATUS_INVALID;
            }
            if ($inLoc !== null && isset($inLoc->within_radius) && ! (bool) $inLoc->within_radius && ($row->attendance_status ?? '') === 'ABSENT') {
                return Attendance::STATUS_INVALID;
            }
            $lateMinutes = (! empty($row->is_late_excused)) ? 0 : (int) ($row->late_minutes ?? 0);
            if ($lateMinutes > 0 || ($row->check_in_status ?? '') === 'LATE' || ($row->attendance_status ?? '') === 'LATE') {
                return Attendance::STATUS_LATE;
            }

            return Attendance::STATUS_ON_TIME;
        }

        return match ((string) ($row->attendance_status ?? '')) {
            'LEAVE' => Attendance::STATUS_LEAVE,
            'PERMISSION' => Attendance::STATUS_PERMISSION,
            'SICK' => Attendance::STATUS_SICK,
            'OFF' => Attendance::STATUS_OFF,
            default => Attendance::STATUS_ABSENT,
        };
    }

    /**
     * @return array{courier_to_emp: array<int, object>, emp_to_courier: array<int, User>}
     */
    protected function resolveCourierMapping(bool $includeInactive = false): array
    {
        if (! $includeInactive && $this->mappingCache !== null) {
            return $this->mappingCache;
        }

        $couriersQuery = User::freelancers()->orderBy('id');
        if (! $includeInactive) {
            $couriersQuery->active();
        }
        $couriers = $couriersQuery->get();

        $employees = $this->db()->table('employees')
            ->select(['id', 'user_id', 'nik', 'full_name', 'phone', 'status', 'employee_type'])
            ->orderByRaw("CASE WHEN status = 'ACTIVE' THEN 0 ELSE 1 END ASC")
            ->orderBy('id')
            ->get();

        $byPhone = [];
        $byNik = [];
        $byName = [];

        foreach ($employees as $emp) {
            $p = self::normalizePhone($emp->phone ?? '');
            if ($p !== '' && ! isset($byPhone[$p])) {
                $byPhone[$p] = $emp;
            }
            $nik = strtolower(trim((string) ($emp->nik ?? '')));
            if ($nik !== '' && ! isset($byNik[$nik])) {
                $byNik[$nik] = $emp;
            }
            $name = self::normalizeName($emp->full_name ?? '');
            if ($name !== '' && ! isset($byName[$name])) {
                $byName[$name] = $emp;
            }
        }

        $courierToEmp = [];
        $empToCourier = [];

        foreach ($couriers as $courier) {
            $phone = self::normalizePhone($courier->phone);
            $nik = strtolower(trim((string) $courier->nik));
            $name = self::normalizeName($courier->name);

            $matched = ($phone !== '' ? ($byPhone[$phone] ?? null) : null)
                ?? ($nik !== '' ? ($byNik[$nik] ?? null) : null)
                ?? ($name !== '' ? ($byName[$name] ?? null) : null);

            if ($matched && ! isset($empToCourier[(int) $matched->id])) {
                $courierToEmp[$courier->id] = $matched;
                $empToCourier[(int) $matched->id] = $courier;
            }
        }

        $mapped = [
            'courier_to_emp' => $courierToEmp,
            'emp_to_courier' => $empToCourier,
        ];

        if (! $includeInactive) {
            $this->mappingCache = $mapped;
        }

        return $mapped;
    }
}
