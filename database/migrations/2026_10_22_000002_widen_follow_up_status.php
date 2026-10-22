<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Status "in_progress" (11 karakter) tidak muat di kolom 10 karakter di MySQL. */
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('follow_up_status', 15)->default('none')->change();
        });
    }

    public function down(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            $table->string('follow_up_status', 10)->default('none')->change();
        });
    }
};
