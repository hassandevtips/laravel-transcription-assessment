<?php

namespace Domain\Transcription\DataTransferObjects;

final readonly class CreateTranscriptionData
{
    public function __construct(public string $audioPath, public string $driver) {}
}
