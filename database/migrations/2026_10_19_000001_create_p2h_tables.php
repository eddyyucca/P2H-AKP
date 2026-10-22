<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();          // mis. LV-12
            $table->string('name', 120);                   // mis. Double cabin 4x4
            $table->string('category', 30)->default('LV')->index();
            $table->string('plate', 30)->nullable();       // mis. DT 8123 KC
            $table->string('notes', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Checklist berversi: hasil P2H menyimpan salinan isian, jadi mengubah checklist tidak mengubah riwayat.
        Schema::create('checklist_versions', function (Blueprint $table) {
            $table->id();
            $table->string('category', 30)->index();
            $table->string('name', 80);
            $table->boolean('is_active')->default(false);
            $table->string('created_by_name', 150)->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_version_id')->constrained('checklist_versions')->cascadeOnDelete();
            $table->string('group_name', 60);              // DOKUMEN, EKSTERIOR, MESIN, KESELAMATAN
            $table->string('label', 200);
            $table->boolean('is_critical')->default(false); // item kritis rusak = unit TIDAK LAYAK
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();        // P2H-LV12-20261004-D
            $table->foreignId('unit_id')->constrained('units');
            $table->string('unit_code', 30);
            $table->string('unit_name', 120);
            $table->string('unit_plate', 30)->nullable();
            $table->foreignId('checklist_version_id')->constrained('checklist_versions');
            $table->string('checklist_version_name', 80);
            $table->dateTime('inspected_at');
            $table->char('shift', 1);                      // D = siang, N = malam
            $table->unsignedInteger('odometer')->nullable();
            $table->string('fuel_level', 20)->nullable();
            $table->string('status', 15)->index();         // layak | tidak_layak
            $table->unsignedSmallInteger('bad_count')->default(0);
            $table->unsignedSmallInteger('critical_bad_count')->default(0);
            $table->text('notes')->nullable();

            // Pemeriksa (disalin dari token Portal).
            $table->string('driver_portal_id', 40)->index();
            $table->string('driver_name', 150);
            $table->string('driver_nik', 40)->nullable();

            // Tindak lanjut temuan: none = tidak ada temuan, open = menunggu, done = sudah ditangani.
            $table->string('follow_up_status', 10)->default('none')->index();
            $table->string('follow_up_by_name', 150)->nullable();
            $table->timestamp('follow_up_at')->nullable();
            $table->string('follow_up_note', 500)->nullable();

            $table->timestamps();

            $table->index(['unit_id', 'inspected_at']);
            $table->index('inspected_at');
        });

        Schema::create('inspection_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->string('group_name', 60);
            $table->string('label', 200);
            $table->boolean('is_critical')->default(false);
            $table->string('result', 3);                   // ok | bad
            $table->string('note', 300)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_answers');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_versions');
        Schema::dropIfExists('units');
    }
};
