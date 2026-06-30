<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SourcePriceResult extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (SourcePriceResult $model): void {
            $model->created_at ??= now();
        });
    }

    protected $fillable = [
        'parser_run_id',
        'position_id',
        'source_id',
        'result_date',
        'external_item_id',
        'title',
        'raw_price',
        'raw_area',
        'normalized_price',
        'currency',
        'is_outlier',
        'is_valid',
        'raw_payload',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'result_date' => 'date:Y-m-d',
            'raw_price' => 'decimal:4',
            'raw_area' => 'decimal:4',
            'normalized_price' => 'decimal:4',
            'is_outlier' => 'boolean',
            'is_valid' => 'boolean',
            'raw_payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function parserRun(): BelongsTo
    {
        return $this->belongsTo(ParserRun::class, 'parser_run_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PricePosition::class, 'position_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PriceSource::class, 'source_id');
    }
}
