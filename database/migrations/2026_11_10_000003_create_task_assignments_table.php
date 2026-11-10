<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('task_number', 30)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained('outlets')->cascadeOnDelete();
            $table->foreignId('task_template_id')->nullable()->constrained('task_templates')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('task_type', ['pasti_harian', 'tidak_pasti'])->default('pasti_harian');
            $table->date('assigned_date');
            $table->time('scheduled_start_time');
            $table->unsignedInteger('estimated_duration_minutes')->default(30);
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->decimal('start_latitude', 10, 8)->nullable();
            $table->decimal('start_longitude', 11, 8)->nullable();
            $table->integer('start_distance_meters')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('end_latitude', 10, 8)->nullable();
            $table->decimal('end_longitude', 11, 8)->nullable();
            $table->integer('end_distance_meters')->nullable();
            $table->unsignedInteger('actual_duration_minutes')->nullable();
            $table->text('notes')->nullable();
            $table->string('proof_image')->nullable();
            $table->boolean('is_location_valid')->nullable();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['user_id', 'assigned_date']);
            $table->index('status');
            $table->index('assigned_date');
            // Mencegah template harian tergenerate ganda untuk user & tanggal yang sama.
            $table->unique(['user_id', 'task_template_id', 'assigned_date'], 'uk_tasks_template_daily');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};
