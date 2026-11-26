<?php

namespace Tests;

use App\Models\User;
use App\Services\HsmAttendanceBridgeService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected string $hsmTestStoragePath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->hsmTestStoragePath = storage_path('framework/testing/hsm_private_'.getmypid());
        File::ensureDirectoryExists($this->hsmTestStoragePath.'/attendance_photos');
        config()->set('services.hsm_attendance.storage_path', $this->hsmTestStoragePath);
        config()->set('services.hsm_attendance.enabled', true);

        $this->bootHsmAttendanceTestSchema();
    }

    protected function tearDown(): void
    {
        if ($this->hsmTestStoragePath !== '' && File::isDirectory($this->hsmTestStoragePath)) {
            File::deleteDirectory($this->hsmTestStoragePath);
        }

        parent::tearDown();
    }

    protected function bootHsmAttendanceTestSchema(): void
    {
        $conn = config('services.hsm_attendance.connection', 'hsm_attendance');
        DB::purge($conn);
        $schema = Schema::connection($conn);

        $schema->dropIfExists('attendance_photos');
        $schema->dropIfExists('attendance_locations');
        $schema->dropIfExists('attendances');
        $schema->dropIfExists('employees');
        $schema->dropIfExists('shifts');
        $schema->dropIfExists('work_locations');

        $schema->create('work_locations', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code', 50)->default('TIKI-SR');
            $t->string('name', 100);
            $t->text('address')->nullable();
            $t->decimal('latitude', 10, 8)->nullable();
            $t->decimal('longitude', 11, 8)->nullable();
            $t->unsignedInteger('radius_meter')->default(25);
            $t->string('status', 20)->default('ACTIVE');
        });

        $schema->create('shifts', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code', 50)->default('PAGI');
            $t->string('name', 100)->default('PAGI');
            $t->time('start_time')->default('08:00:00');
            $t->time('end_time')->default('17:00:00');
            $t->string('status', 20)->default('ACTIVE');
        });

        $schema->create('employees', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('nik', 50);
            $t->string('full_name', 150);
            $t->string('phone', 20);
            $t->string('status', 20)->default('ACTIVE');
            $t->string('employee_type', 20)->default('REGULAR');
        });

        $schema->create('attendances', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('employee_id');
            $t->date('work_date');
            $t->unsignedInteger('shift_id')->default(1);
            $t->unsignedInteger('work_location_id')->default(1);
            $t->dateTime('scheduled_check_in')->nullable();
            $t->dateTime('scheduled_check_out')->nullable();
            $t->dateTime('actual_check_in')->nullable();
            $t->dateTime('actual_check_out')->nullable();
            $t->string('check_in_status', 20)->nullable();
            $t->string('check_out_status', 20)->nullable();
            $t->unsignedInteger('late_minutes')->default(0);
            $t->boolean('is_late_excused')->default(false);
            $t->string('attendance_status', 30)->default('PRESENT');
            $t->string('validation_status', 30)->default('VALID');
            $t->text('validation_note')->nullable();
            $t->timestamps();
        });

        $schema->create('attendance_locations', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedBigInteger('attendance_id');
            $t->string('type', 20);
            $t->decimal('latitude', 10, 8);
            $t->decimal('longitude', 11, 8);
            $t->decimal('accuracy_meter', 8, 2)->nullable();
            $t->decimal('distance_meter', 10, 2)->nullable();
            $t->unsignedInteger('work_location_id')->default(1);
            $t->boolean('within_radius')->default(true);
            $t->string('integrity_status', 20)->default('NORMAL');
            $t->dateTime('captured_at');
        });

        $schema->create('attendance_photos', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedBigInteger('attendance_id');
            $t->string('type', 20);
            $t->string('file_path', 255);
            $t->string('file_hash', 64)->default('hash');
            $t->string('mime_type', 50)->default('image/jpeg');
            $t->unsignedInteger('file_size')->default(1024);
            $t->dateTime('captured_at');
        });

        DB::connection($conn)->table('work_locations')->insert([
            'id' => 1,
            'code' => 'TIKI-SR',
            'name' => 'TIKI SR',
            'address' => 'JL. SLAMET RIYADI NO.48',
            'latitude' => -0.50880800,
            'longitude' => 117.11790400,
            'radius_meter' => 25,
            'status' => 'ACTIVE',
        ]);

        DB::connection($conn)->table('shifts')->insert([
            'id' => 1,
            'code' => 'PAGI',
            'name' => 'PAGI',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'status' => 'ACTIVE',
        ]);

        app(HsmAttendanceBridgeService::class)->resetCache();
    }

    protected function ensureHsmEmployee(User $courier, array $overrides = []): int
    {
        $conn = config('services.hsm_attendance.connection', 'hsm_attendance');
        $db = DB::connection($conn);

        $phone = $overrides['phone'] ?? $courier->phone;
        $existing = $db->table('employees')->where('user_id', $courier->id)->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $empId = (int) $db->table('employees')->insertGetId([
            'user_id' => $courier->id,
            'nik' => $overrides['nik'] ?? ('647200000000'.str_pad((string) $courier->id, 4, '0', STR_PAD_LEFT)),
            'full_name' => $overrides['full_name'] ?? $courier->name,
            'phone' => $phone,
            'status' => $overrides['status'] ?? 'ACTIVE',
            'employee_type' => $overrides['employee_type'] ?? 'REGULAR',
        ]);

        app(HsmAttendanceBridgeService::class)->resetCache();

        return $empId;
    }

    protected function seedHsmCheckIn(User $courier, array $overrides = []): int
    {
        $conn = config('services.hsm_attendance.connection', 'hsm_attendance');
        $db = DB::connection($conn);
        $empId = $overrides['employee_id'] ?? $this->ensureHsmEmployee($courier, $overrides['employee'] ?? []);

        $workDate = $overrides['work_date'] ?? today()->toDateString();
        $checkInAt = $overrides['actual_check_in'] ?? ($workDate.' 07:45:00');
        $lateMinutes = (int) ($overrides['late_minutes'] ?? 0);

        $attId = (int) $db->table('attendances')->insertGetId([
            'employee_id' => $empId,
            'work_date' => $workDate,
            'shift_id' => $overrides['shift_id'] ?? 1,
            'work_location_id' => $overrides['work_location_id'] ?? 1,
            'scheduled_check_in' => $workDate.' 08:00:00',
            'scheduled_check_out' => $workDate.' 17:00:00',
            'actual_check_in' => $checkInAt,
            'actual_check_out' => $overrides['actual_check_out'] ?? null,
            'check_in_status' => $overrides['check_in_status'] ?? ($lateMinutes > 0 ? 'LATE' : 'ON_TIME'),
            'late_minutes' => $lateMinutes,
            'is_late_excused' => (bool) ($overrides['is_late_excused'] ?? false),
            'attendance_status' => $overrides['attendance_status'] ?? ($lateMinutes > 0 ? 'LATE' : 'PRESENT'),
            'validation_status' => $overrides['validation_status'] ?? 'VALID',
            'validation_note' => $overrides['validation_note'] ?? null,
            'created_at' => $checkInAt,
            'updated_at' => $checkInAt,
        ]);

        $db->table('attendance_locations')->insert([
            'attendance_id' => $attId,
            'type' => 'CHECK_IN',
            'latitude' => $overrides['latitude'] ?? -0.50875050,
            'longitude' => $overrides['longitude'] ?? 117.11792790,
            'accuracy_meter' => $overrides['accuracy_meter'] ?? 8.0,
            'distance_meter' => $overrides['distance_meter'] ?? 6.9,
            'work_location_id' => $overrides['work_location_id'] ?? 1,
            'within_radius' => $overrides['within_radius'] ?? true,
            'integrity_status' => 'NORMAL',
            'captured_at' => $checkInAt,
        ]);

        if (($overrides['with_photo'] ?? true) === true) {
            $relPath = 'attendance_photos/checkin_'.$attId.'.jpg';
            $absPath = $this->hsmTestStoragePath.'/'.$relPath;
            File::ensureDirectoryExists(dirname($absPath));
            // Minimal valid JPEG header bytes for testing file stream
            File::put($absPath, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9");

            $db->table('attendance_photos')->insert([
                'attendance_id' => $attId,
                'type' => 'CHECK_IN',
                'file_path' => $relPath,
                'file_hash' => hash('sha256', $relPath),
                'mime_type' => 'image/jpeg',
                'file_size' => filesize($absPath),
                'captured_at' => $checkInAt,
            ]);
        }

        app(HsmAttendanceBridgeService::class)->resetCache();

        return $attId;
    }

    protected function seedHsmCheckOut(int $hsmAttendanceId, array $overrides = []): void
    {
        $conn = config('services.hsm_attendance.connection', 'hsm_attendance');
        $db = DB::connection($conn);

        $att = $db->table('attendances')->where('id', $hsmAttendanceId)->first();
        $workDate = $att->work_date ?? today()->toDateString();
        $checkOutAt = $overrides['actual_check_out'] ?? ($workDate.' 17:05:00');

        $db->table('attendances')
            ->where('id', $hsmAttendanceId)
            ->update([
                'actual_check_out' => $checkOutAt,
                'check_out_status' => $overrides['check_out_status'] ?? 'ON_TIME',
                'updated_at' => $checkOutAt,
            ]);

        $db->table('attendance_locations')->insert([
            'attendance_id' => $hsmAttendanceId,
            'type' => 'CHECK_OUT',
            'latitude' => $overrides['latitude'] ?? -0.50876570,
            'longitude' => $overrides['longitude'] ?? 117.11792560,
            'accuracy_meter' => $overrides['accuracy_meter'] ?? 6.5,
            'distance_meter' => $overrides['distance_meter'] ?? 5.3,
            'work_location_id' => 1,
            'within_radius' => $overrides['within_radius'] ?? true,
            'integrity_status' => 'NORMAL',
            'captured_at' => $checkOutAt,
        ]);

        if (($overrides['with_photo'] ?? true) === true) {
            $relPath = 'attendance_photos/checkout_'.$hsmAttendanceId.'.jpg';
            $absPath = $this->hsmTestStoragePath.'/'.$relPath;
            File::ensureDirectoryExists(dirname($absPath));
            File::put($absPath, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9");

            $db->table('attendance_photos')->insert([
                'attendance_id' => $hsmAttendanceId,
                'type' => 'CHECK_OUT',
                'file_path' => $relPath,
                'file_hash' => hash('sha256', $relPath),
                'mime_type' => 'image/jpeg',
                'file_size' => filesize($absPath),
                'captured_at' => $checkOutAt,
            ]);
        }

        app(HsmAttendanceBridgeService::class)->resetCache();
    }

    protected function seedHsmLeave(User $courier, string $status = 'LEAVE', ?string $note = 'Cuti: Cuti Tahunan', ?string $workDate = null): int
    {
        $conn = config('services.hsm_attendance.connection', 'hsm_attendance');
        $db = DB::connection($conn);
        $empId = $this->ensureHsmEmployee($courier);
        $date = $workDate ?? today()->toDateString();

        $attId = (int) $db->table('attendances')->insertGetId([
            'employee_id' => $empId,
            'work_date' => $date,
            'shift_id' => 1,
            'work_location_id' => 1,
            'actual_check_in' => null,
            'actual_check_out' => null,
            'attendance_status' => $status,
            'validation_status' => 'VALID',
            'validation_note' => $note,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        app(HsmAttendanceBridgeService::class)->resetCache();

        return $attId;
    }
}
