<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CityPriceSectionItem extends Model
{
    protected $fillable = [
        'tab_id',
        'position_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'tab_id' => 'integer',
            'position_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function tab(): BelongsTo
    {
        return $this->belongsTo(CityPriceSectionTab::class, 'tab_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PricePosition::class, 'position_id');
    }
}

