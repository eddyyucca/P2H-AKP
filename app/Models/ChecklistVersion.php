<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistVersion extends Model
{
    protected $fillable = ['category', 'name', 'is_active', 'created_by_name', 'activated_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'activated_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function activeFor(string $category): ?self
    {
        return static::where('category', $category)->where('is_active', true)->latest('id')->first();
    }

    /** Menjadikan versi ini satu-satunya yang aktif untuk kategorinya. */
    public function activate(): void
    {
        static::where('category', $this->category)->where('id', '!=', $this->id)->update(['is_active' => false]);
        $this->update(['is_active' => true, 'activated_at' => now()]);
    }

    /** Salinan (nonaktif) beserta semua itemnya, untuk disunting tanpa mengganggu versi yang sedang dipakai. */
    public function duplicate(string $name, ?string $by): self
    {
        $copy = static::create(['category' => $this->category, 'name' => $name, 'is_active' => false, 'created_by_name' => $by]);

        foreach ($this->items as $item) {
            $copy->items()->create($item->only(['group_name', 'label', 'is_critical', 'sort_order']));
        }

        return $copy;
    }
}
