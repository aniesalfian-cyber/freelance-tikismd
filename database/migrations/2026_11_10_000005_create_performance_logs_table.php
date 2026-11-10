<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('performance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('evaluation_date');
            $table->unsignedInteger('total_tasks')->default(0);
            $table->unsignedInteger('completed_tasks')->default(0);
            $table->unsignedInteger('on_time_tasks')->default(0);
            $table->unsignedInteger('valid_location_tasks')->default(0);
            $table->unsignedInteger('total_work_minutes')->default(0);
            $table->decimal('completion_score', 5, 2)->default(0);
            $table->decimal('time_score', 5, 2)->default(0);
            $table->decimal('location_score', 5, 2)->default(0);
            $table->decimal('attendance_score', 5, 2)->default(0);
            $table->decimal('final_kpi_score', 5, 2)->default(0);
            $table->enum('grade', ['A', 'B', 'C', 'D'])->default('C')->index();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'evaluation_date'], 'uk_user_perf_date');
            $table->index('evaluation_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_logs');
    }
};
