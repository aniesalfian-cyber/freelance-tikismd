<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->date('date');
            $table->timestamp('check_in_at');
            $table->decimal('check_in_latitude', 10, 8);
            $table->decimal('check_in_longitude', 11, 8);
            $table->integer('check_in_distance_meters');
            $table->decimal('check_in_accuracy', 8, 2)->nullable();
            $table->unsignedInteger('late_minutes')->default(0);
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();
            $table->integer('check_out_distance_meters')->nullable();
            $table->enum('status', ['hadir_tepat', 'terlambat', 'invalid_radius'])->default('hadir_tepat');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'date'], 'uk_user_daily_attendance');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
