<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceSource extends Model
{
    protected $fillable = [
        'position_id',
        'links_json',
        'options_json',
        'source_type',
        'is_active',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'links_json' => 'array',
            'options_json' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PricePosition::class, 'position_id');
    }

    public function sourcePriceResults(): HasMany
    {
        return $this->hasMany(SourcePriceResult::class, 'source_id');
    }
}
