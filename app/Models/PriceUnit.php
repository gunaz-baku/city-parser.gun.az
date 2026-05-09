<?php

namespace App\Models;

use App\Http\Support\AdminApiReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceUnit extends Model
{
    protected $fillable = [
        'code',
        'label',
        'sort_order',
        'is_active',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saved(static function (): void {
            AdminApiReferenceCache::forgetPriceUnits();
        });

        static::deleted(static function (): void {
            AdminApiReferenceCache::forgetPriceUnits();
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function pricePositions(): HasMany
    {
        return $this->hasMany(PricePosition::class, 'price_unit_id');
    }

    public function displayLabel(): string
    {
        $label = trim((string) ($this->label ?? ''));

        return $label !== '' ? $label : '#'.(string) $this->id;
    }
}
