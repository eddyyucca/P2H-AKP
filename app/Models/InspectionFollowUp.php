<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu langkah dalam riwayat tindak lanjut sebuah temuan P2H. */
class InspectionFollowUp extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['inspection_id', 'status', 'note', 'pic', 'due_date', 'by_name'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'created_at' => 'datetime'];
    }

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class);
    }
}
