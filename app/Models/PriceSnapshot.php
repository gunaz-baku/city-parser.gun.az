<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceSnapshot extends Model
{
    protected $fillable = [
        'position_id',
        'snapshot_date',
        'currency',
        'price_min',
        'price_max',
        'price_avg',
        'sample_size',
        'source_count',
        'parser_type',
        'parser_run_id',
        'sync_status',
        'synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'price_min' => 'decimal:4',
            'price_max' => 'decimal:4',
            'price_avg' => 'decimal:4',
            'synced_at' => 'datetime',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(PricePosition::class, 'position_id');
    }

    public function parserRun(): BelongsTo
    {
        return $this->belongsTo(ParserRun::class, 'parser_run_id');
    }
}
