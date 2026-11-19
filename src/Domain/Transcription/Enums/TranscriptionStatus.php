<?php

namespace Domain\Transcription\Enums;

enum TranscriptionStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Retrying = 'retrying';
    case Completed = 'completed';
    case Failed = 'failed';
}
