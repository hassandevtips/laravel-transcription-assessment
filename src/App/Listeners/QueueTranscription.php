<?php

namespace App\Listeners;

use App\Jobs\TranscribeAudio;
use Domain\Transcription\Events\TranscriptionRequested;

final class QueueTranscription
{
    public function handle(TranscriptionRequested $event): void
    {
        // Database queue inserts participate in the action's transaction.
        TranscribeAudio::dispatch($event->transcriptionId);
    }
}
