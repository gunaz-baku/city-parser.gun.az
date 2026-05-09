<?php

namespace App\Models;

use App\Models\Concerns\HasTranslatedJsonLabels;
use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    use HasTranslatedJsonLabels;

    protected $fillable = [
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
