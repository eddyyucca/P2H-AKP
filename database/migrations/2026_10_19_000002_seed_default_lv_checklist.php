<?php

use App\Support\DefaultChecklist;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** Checklist bawaan untuk kendaraan LV (dari layar P2H di aplikasi mobile). Hanya dibuat bila belum ada checklist LV. */
    public function up(): void
    {
        DefaultChecklist::seed('LV', 'Versi Sep 2026');
    }

    public function down(): void
    {
        // Data checklist dibiarkan: dihapus bersama tabelnya oleh migrasi sebelumnya.
    }
};
