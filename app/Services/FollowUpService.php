<?php

namespace App\Services;

use App\Models\Inspection;
use Illuminate\Support\Facades\DB;

/** Alur tindak lanjut temuan P2H: open -> in_progress -> done, setiap langkah tercatat di riwayat. */
class FollowUpService
{
    /** Menandai temuan mulai dikerjakan: PIC/bengkel dan (opsional) tenggat. */
    public function start(Inspection $inspection, string $by, string $pic, ?string $dueDate, ?string $note): void
    {
        DB::transaction(function () use ($inspection, $by, $pic, $dueDate, $note) {
            $inspection->update(['follow_up_status' => 'in_progress', 'follow_up_pic' => $pic, 'follow_up_due' => $dueDate]);
            $inspection->followUps()->create([
                'status' => 'in_progress', 'pic' => $pic, 'due_date' => $dueDate, 'note' => filled($note) ? trim($note) : null, 'by_name' => $by,
            ]);
        });
    }

    /** Menutup temuan dengan catatan penanganan. */
    public function close(Inspection $inspection, string $by, string $note, ?string $resolvedBy = null): void
    {
        DB::transaction(function () use ($inspection, $by, $note, $resolvedBy) {
            $inspection->update([
                'follow_up_status' => 'done', 'follow_up_by_name' => $by, 'follow_up_at' => now(),
                'follow_up_note' => trim($note), 'resolved_by_number' => $resolvedBy,
            ]);
            $inspection->followUps()->create(['status' => 'done', 'note' => trim($note), 'by_name' => $by]);
        });
    }

    /**
     * P2H ulang tanpa temuan pada unit yang sama menutup semua temuan terbuka unit itu yang lebih lama:
     * unit sudah diperiksa dan semua item baik.
     */
    public function autoResolveFor(Inspection $clean): int
    {
        $open = Inspection::openFindings()->where('unit_id', $clean->unit_id)->where('id', '<', $clean->id)->get();

        foreach ($open as $old) {
            $this->close($old, 'Sistem', "Selesai otomatis: P2H ulang {$clean->number} tanpa temuan.", $clean->number);
        }

        return $open->count();
    }
}
