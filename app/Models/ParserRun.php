<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParserRun extends Model
{
    protected $fillable = [
        'parser_type',
        'trigger_type',
        'status',
        'started_at',
        'finished_at',
        'duration_seconds',
        'total_positions',
        'success_positions',
        'failed_positions',
        'skipped_positions',
        'total_sources',
        'success_sources',
        'failed_sources',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function parserRunErrors(): HasMany
    {
        return $this->hasMany(ParserRunError::class, 'parser_run_id');
    }

    public function sourcePriceResults(): HasMany
    {
        return $this->hasMany(SourcePriceResult::class, 'parser_run_id');
    }

    public function priceSnapshots(): HasMany
    {
        return $this->hasMany(PriceSnapshot::class, 'parser_run_id');
    }

    public function apiSyncLogs(): HasMany
    {
        return $this->hasMany(ApiSyncLog::class, 'parser_run_id');
    }
}
