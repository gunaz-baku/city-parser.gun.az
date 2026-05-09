<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesParserRunLifecycle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use InvalidArgumentException;

class StartParserJob implements ShouldQueue
{
    use Dispatchable, HandlesParserRunLifecycle, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public function __construct(
        public string $parserType,
        public string $triggerType = 'cron',
    ) {
        $this->onConnection('redis');
        $this->onQueue('parser-orchestration');
    }

    public function handle(): void
    {
        $runId = $this->createParserRun($this->parserType, $this->triggerType);
        if ($runId === null) {
            throw new \RuntimeException('parser_runs cədvəli mövcud deyil.');
        }

        match ($this->parserType) {
            'wolt' => WoltParserJob::dispatch($runId),
            'bina' => BinaParserJob::dispatch($runId),
            default => throw new InvalidArgumentException("Unsupported parser_type: {$this->parserType}"),
        };
    }
}
