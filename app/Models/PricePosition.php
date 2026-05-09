<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedJsonLabels;
use App\Support\LocalizedJson;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PricePosition extends Model
{
    use HasTranslatedJsonLabels;

    protected $fillable = [
        'category_id',
        'slug',
        'name',
        'unit_id',
        'unit_size',
        'parser_type',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'unit_size' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PriceCategory::class, 'category_id');
    }

    /**
     * Normalized unit row (`units` table). Named `measurementUnit` to avoid clashing with the legacy JSON `unit` attribute.
     */
    public function measurementUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }

    public function priceSources(): HasMany
    {
        return $this->hasMany(PriceSource::class, 'position_id');
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class, 'position_id');
    }

    /**
     * Latest snapshot for this product in its own city (for basket estimates).
     */
    public function latestPriceSnapshot(): HasOne
    {
        return $this->hasOne(PriceSnapshot::class, 'position_id')
            ->ofMany(
                ['snapshot_date' => 'max', 'id' => 'max'],
            );
    }

    public function basketItems(): HasMany
    {
        return $this->hasMany(BasketItem::class, 'position_id');
    }

    public function getUnitLabelEnAttribute(): ?string
    {
        $mu = $this->relationLoaded('measurementUnit') ? $this->getRelation('measurementUnit') : null;
        if ($mu instanceof Unit) {
            $s = trim((string) ($mu->short_name ?? ''));
            if ($s !== '') {
                return $s;
            }
            $n = trim((string) ($mu->name ?? ''));

            return $n !== '' ? $n : null;
        }

        return null;
    }
}
