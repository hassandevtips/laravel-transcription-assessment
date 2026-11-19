<?php

namespace App\Jobs;

use Domain\Transcription\Actions\FailTranscriptionAction;
use Domain\Transcription\Actions\TranscribeAudioAction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class TranscribeAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 0;

    public int $maxExceptions = 3;

    public int $retryDeadline;

    public int $timeout = 3600;

    public bool $failOnTimeout = true;

    public function __construct(public string $transcriptionId)
    {
        $this->onQueue('transcriptions');
        $this->retryDeadline = time() + 14400;
    }

    public function retryUntil(): \DateTimeInterface
    {
        return new \DateTimeImmutable('@'.$this->retryDeadline);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('transcription:'.$this->transcriptionId))
            ->releaseAfter(60)->expireAfter(3660)];
    }

    public function handle(TranscribeAudioAction $action): void
    {
        $action->execute($this->transcriptionId);
    }

    public function failed(?Throwable $exception): void
    {
        app(FailTranscriptionAction::class)->execute($this->transcriptionId);
        if ($exception) {
            report($exception);
        }
    }
}
