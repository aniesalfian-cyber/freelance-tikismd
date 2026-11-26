<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropUnique('outlets_code_unique');
            $table->string('code', 40)->change();
            $table->index('code', 'outlets_code_index');
        });
    }

    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropIndex('outlets_code_index');
            $table->string('code', 20)->unique('outlets_code_unique')->change();
        });
    }
};
