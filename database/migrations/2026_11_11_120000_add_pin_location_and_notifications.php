<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pin')->nullable()->after('password');
            $table->decimal('last_latitude', 10, 7)->nullable()->after('last_login_ip');
            $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            $table->integer('last_accuracy_meters')->nullable()->after('last_longitude');
            $table->timestamp('last_location_at')->nullable()->after('last_accuracy_meters');
        });

        // Set default PIN 1234 untuk semua akun yang sudah ada
        $defaultHashedPin = Hash::make('1234');
        DB::table('users')->whereNull('pin')->update(['pin' => $defaultHashedPin]);

        Schema::create('app_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->text('body');
            $table->string('url')->nullable();
            $table->string('type', 50)->default('task_assigned')->index();
            $table->unsignedBigInteger('task_id')->nullable()->index();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('pushed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_notifications');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pin',
                'last_latitude',
                'last_longitude',
                'last_accuracy_meters',
                'last_location_at',
            ]);
        });
    }
};
