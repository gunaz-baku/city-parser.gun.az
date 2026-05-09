<?php

namespace App\Jobs;

use App\Actions\RecalculateBasketSnapshotsAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AggregateBasketSnapshotsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(public int $parserRunId)
    {
        $this->onConnection('redis');
        $this->onQueue('parser-orchestration');
    }

    public function handle(): void
    {
        app(RecalculateBasketSnapshotsAction::class)->run(
            parserRunId: $this->parserRunId,
            snapshotDate: null,
        );
    }
}
