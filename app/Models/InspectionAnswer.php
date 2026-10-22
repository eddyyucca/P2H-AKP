<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InspectionAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = ['inspection_id', 'group_name', 'label', 'is_critical', 'result', 'note', 'photo_path', 'sort_order'];

    protected function casts(): array
    {
        return ['is_critical' => 'boolean'];
    }

    public function isBad(): bool
    {
        return $this->result === 'bad';
    }
}
