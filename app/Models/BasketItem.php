<?php

namespace App\Models;

use App\Http\Support\AdminApiReferenceCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasketItem extends Model
{
    protected $fillable = [
        'basket_id',
        'position_id',
        'qty',
        'unit_id',
        'qty_unit',
    ];

    protected static function booted(): void
    {
        parent::booted();

        static::saving(static function (BasketItem $item): void {
            if ($item->unit_id !== null) {
                $u = $item->relationLoaded('unit')
                    ? $item->getRelation('unit')
                    : Unit::query()->find($item->unit_id);
        
                if ($u instanceof Unit) {
                    $short = trim((string) ($u->short_name ?? ''));
                    $code = trim((string) ($u->code ?? ''));
        
                    $item->qty_unit = $short !== '' ? $short : ($code !== '' ? $code : 'pcs');
        
                    return;
                }
            }
        
            if (! filled($item->qty_unit)) {
                $item->qty_unit = 'pcs';
            }
        });

        static::saved(static function (): void {
            AdminApiReferenceCache::forgetBasketDefinitionsIndex();
        });

        static::deleted(static function (): void {
            AdminApiReferenceCache::forgetBasketDefinitionsIndex();
        });
    }

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
        ];
    }

    public function basket(): BelongsTo
    {
        return $this->belongsTo(BasketDefinition::class, 'basket_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PricePosition::class, 'position_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
