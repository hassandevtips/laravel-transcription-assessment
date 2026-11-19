<?php

namespace Domain\Transcription\Contracts;

use Domain\Transcription\DataTransferObjects\TranscriptData;

interface AudioTranscriber
{
    public function transcribe(string $audioPath, string $driver): TranscriptData;
}
