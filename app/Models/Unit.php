<?php

namespace App\Models;

use App\Http\Support\AdminApiReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    protected $fillable = [
        'code',
        'name',
        'short_name',
        'unit_type',
        'base_unit',
        'multiplier',
        'is_active',
        'sort_order',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saved(static function (): void {
            AdminApiReferenceCache::forgetUnits();
        });

        static::deleted(static function (): void {
            AdminApiReferenceCache::forgetUnits();
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'multiplier' => 'decimal:6',
        ];
    }

    public function pricePositions(): HasMany
    {
        return $this->hasMany(PricePosition::class, 'unit_id');
    }

    public function basketItems(): HasMany
    {
        return $this->hasMany(BasketItem::class, 'unit_id');
    }

    public function displayLabel(): string
    {
        $name = trim((string) ($this->name ?? ''));
        $short = trim((string) ($this->short_name ?? ''));

        if ($short !== '' && $name !== '' && mb_strtolower($short) !== mb_strtolower($name)) {
            return $name.' ('.$short.')';
        }

        return $name !== '' ? $name : ($short !== '' ? $short : '#'.(string) $this->id);
    }
}
