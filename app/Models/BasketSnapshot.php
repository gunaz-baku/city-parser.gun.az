<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class BasketSnapshot extends Model
{
    protected $fillable = [
        'basket_id',
        'snapshot_date',
        'total_price',
        'dolma_index_total',
        'currency',
        'sync_status',
        'synced_at',
        'last_sync_error',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date:Y-m-d',
            'total_price' => 'decimal:4',
            'dolma_index_total' => 'decimal:4',
            'synced_at' => 'datetime',
        ];
    }

    public function basket(): BelongsTo
    {
        return $this->belongsTo(BasketDefinition::class, 'basket_id');
    }

    /**
     * Latest stored snapshot on or before {@code snapshot_date - $daysBack} days (same basket).
     */
    public function comparisonSnapshotDaysAgo(int $daysBack): ?self
    {
        $needle = Carbon::parse((string) $this->snapshot_date)->subDays($daysBack)->toDateString();

        return static::query()
            ->where('basket_id', $this->basket_id)
            ->whereDate('snapshot_date', '<=', $needle)
            ->whereKeyNot($this->getKey())
            ->orderByDesc('snapshot_date')
            ->first();
    }

    public function percentChangeVersus(?self $prior): ?float
    {
        if ($prior === null) {
            return null;
        }

        $prev = (float) $prior->total_price;
        if (abs($prev) < 1e-12) {
            return null;
        }

        $cur = (float) $this->total_price;

        return (($cur - $prev) / $prev) * 100.0;
    }

    public function formatPercentChange(?float $pct): string
    {
        if ($pct === null) {
            return '—';
        }

        $sign = $pct > 0 ? '+' : '';

        return $sign.number_format($pct, 1).'%';
    }
}
