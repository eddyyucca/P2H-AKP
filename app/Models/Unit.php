<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Satu unit (kendaraan/alat) yang diperiksa. */
class Unit extends Model
{
    protected $fillable = ['code', 'name', 'category', 'plate', 'notes', 'service_interval_km', 'last_service_km', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Odometer yang dilaporkan di P2H terbaru unit ini (null bila belum pernah). */
    public function lastOdometer(): ?int
    {
        $value = $this->inspections()->whereNotNull('odometer')->orderByDesc('inspected_at')->orderByDesc('id')->value('odometer');

        return $value === null ? null : (int) $value;
    }

    /** Sisa km menuju servis berikutnya (negatif = sudah lewat); null bila jadwal servis belum diatur. */
    public static function serviceRemaining(?int $interval, ?int $lastService, ?int $odometer): ?int
    {
        if (! $interval || $odometer === null) {
            return null;
        }

        return ($lastService ?? 0) + $interval - $odometer;
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }
}
