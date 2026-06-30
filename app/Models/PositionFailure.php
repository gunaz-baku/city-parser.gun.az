<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionFailure extends Model
{
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (PositionFailure $model): void {
            $model->created_at ??= now();
        });
    }

    protected $fillable = [
        'parser_run_id',
        'position_id',
        'failure_date',
        'reason',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'failure_date' => 'date:Y-m-d',
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
}
