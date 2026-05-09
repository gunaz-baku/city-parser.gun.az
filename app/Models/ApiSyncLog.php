<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiSyncLog extends Model
{
    protected $fillable = [
        'parser_run_id',
        'entity_type',
        'entity_id',
        'endpoint',
        'request_payload',
        'response_status',
        'response_body',
        'status',
        'error_message',
        'attempt',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function parserRun(): BelongsTo
    {
        return $this->belongsTo(ParserRun::class, 'parser_run_id');
    }
}
