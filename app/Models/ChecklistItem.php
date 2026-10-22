<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChecklistItem extends Model
{
    protected $fillable = ['checklist_version_id', 'group_name', 'label', 'is_critical', 'sort_order'];

    protected function casts(): array
    {
        return ['is_critical' => 'boolean'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }
}
