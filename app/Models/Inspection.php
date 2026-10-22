<?php

namespace App\Models;

use Akp\PortalSso\PortalUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu hasil P2H. Data unit, checklist, dan pemeriksa disalin saat disimpan sehingga riwayat tidak berubah bila master diubah. */
class Inspection extends Model
{
    public const SHIFTS = ['D' => 'Siang', 'N' => 'Malam'];

    protected $fillable = [
        'number', 'unit_id', 'unit_code', 'unit_name', 'unit_plate', 'checklist_version_id', 'checklist_version_name',
        'inspected_at', 'shift', 'odometer', 'fuel_level', 'status', 'bad_count', 'critical_bad_count', 'notes',
        'driver_portal_id', 'driver_name', 'driver_nik',
        'follow_up_status', 'follow_up_by_name', 'follow_up_at', 'follow_up_note', 'follow_up_pic', 'follow_up_due', 'resolved_by_number',
        'declared_at',
    ];

    /** Status tindak lanjut yang masih terbuka (belum selesai). */
    public const OPEN_STATES = ['open', 'in_progress'];

    protected function casts(): array
    {
        return ['inspected_at' => 'datetime', 'follow_up_at' => 'datetime', 'follow_up_due' => 'date', 'declared_at' => 'datetime'];
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(InspectionFollowUp::class)->orderBy('id');
    }

    public function isOpen(): bool
    {
        return in_array($this->follow_up_status, self::OPEN_STATES, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->follow_up_due !== null && $this->follow_up_due->lt(today());
    }

    public function scopeOpenFindings(Builder $query): Builder
    {
        return $query->whereIn('follow_up_status', self::OPEN_STATES);
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(InspectionAnswer::class)->orderBy('sort_order')->orderBy('id');
    }

    public function isFit(): bool
    {
        return $this->status === 'layak';
    }

    public function shiftLabel(): string
    {
        return self::SHIFTS[$this->shift] ?? $this->shift;
    }

    /** Level yang melihat semua P2H: administrator, pengawas, dan viewer. Operator hanya miliknya sendiri. */
    public static function seesAll(PortalUser $user): bool
    {
        return $user->hasRole('admin', 'pengawas', 'viewer');
    }

    public function scopeVisibleTo(Builder $query, PortalUser $user): Builder
    {
        return self::seesAll($user) ? $query : $query->where('driver_portal_id', (string) $user->id);
    }
}
