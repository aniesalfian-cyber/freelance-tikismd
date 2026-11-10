<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->enum('task_type', ['pasti_harian', 'tidak_pasti'])->default('pasti_harian');
            $table->time('scheduled_start_time')->default('08:30:00');
            $table->unsignedInteger('estimated_duration_minutes')->default(30);
            $table->foreignId('default_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('default_user_id')->nullable()->constrained('users')->nullOnDelete();
            // Hari berlaku: array angka ISO 1 (Senin) - 7 (Minggu). NULL = setiap hari.
            $table->json('active_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_templates');
    }
};
