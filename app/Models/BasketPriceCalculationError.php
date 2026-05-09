<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BasketPriceCalculationError extends Model
{
    protected $fillable = [
        'basket_id',
        'basket_item_id',
        'position_id',
        'error_type',
        'message',
        'context',
        'calculation_run_id',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}