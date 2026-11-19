<?php

namespace Domain\Transcription\Events;

final readonly class TranscriptionCompleted
{
    public function __construct(public string $transcriptionId) {}
}
