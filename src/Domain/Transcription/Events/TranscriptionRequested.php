<?php

namespace Domain\Transcription\Events;

final readonly class TranscriptionRequested
{
    public function __construct(public string $transcriptionId) {}
}
