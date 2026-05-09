<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedJsonLabels;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceCategory extends Model
{
    use HasTranslatedJsonLabels;

    protected $fillable = [
        'slug',
        'name',
        'icon',
        'sort_order',
        'is_active',
        'show_in_page',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
            'show_in_page' => 'boolean',
        ];
    }

    public function pricePositions(): HasMany
    {
        return $this->hasMany(PricePosition::class, 'category_id');
    }
}
