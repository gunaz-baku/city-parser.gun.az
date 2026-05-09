<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParserRunError extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (ParserRunError $model): void {
            $model->created_at ??= now();
        });
    }

    protected $fillable = [
        'parser_run_id',
        'position_id',
        'source_id',
        'error_stage',
        'error_code',
        'error_message',
        'error_context',
        'occurred_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'error_context' => 'array',
            'occurred_at' => 'datetime',
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
