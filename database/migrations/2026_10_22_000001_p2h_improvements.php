<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inspections', function (Blueprint $table) {
            // Alur tindak lanjut: open -> in_progress -> done. PIC dan tenggat diisi saat mulai dikerjakan.
            $table->string('follow_up_pic', 100)->nullable()->after('follow_up_note');
            $table->date('follow_up_due')->nullable()->after('follow_up_pic');
            $table->string('resolved_by_number', 60)->nullable()->after('follow_up_due'); // P2H ulang yang menutup temuan ini
            $table->timestamp('declared_at')->nullable()->after('notes');                   // pemeriksa menyatakan hasil benar

            $table->index(['follow_up_status', 'inspected_at']);
        });

        Schema::table('inspection_answers', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable()->after('note');
        });

        Schema::table('units', function (Blueprint $table) {
            // Pengingat servis berdasarkan odometer (opsional).
            $table->unsignedInteger('service_interval_km')->nullable()->after('notes');
            $table->unsignedInteger('last_service_km')->nullable()->after('service_interval_km');
        });

        Schema::create('inspection_follow_ups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->string('status', 12);                  // open | in_progress | done
            $table->string('note', 500)->nullable();
            $table->string('pic', 100)->nullable();
            $table->date('due_date')->nullable();
            $table->string('by_name', 150);
            $table->timestamp('created_at')->useCurrent();

            $table->index('inspection_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_follow_ups');

        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['service_interval_km', 'last_service_km']);
        });
        Schema::table('inspection_answers', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });
        Schema::table('inspections', function (Blueprint $table) {
            $table->dropIndex(['follow_up_status', 'inspected_at']);
            $table->dropColumn(['follow_up_pic', 'follow_up_due', 'resolved_by_number', 'declared_at']);
        });
    }
};
