<?php

namespace Domain\Transcription\Actions;

use Domain\Transcription\Enums\TranscriptionStatus;
use Domain\Transcription\Models\Transcription;

final class FailTranscriptionAction
{
    public function execute(string $id): void
    {
        Transcription::whereKey($id)->where('status', '!=', TranscriptionStatus::Completed->value)
            ->update(['status' => TranscriptionStatus::Failed->value, 'error' => 'Transcription failed. Please retry.']);
    }
}
