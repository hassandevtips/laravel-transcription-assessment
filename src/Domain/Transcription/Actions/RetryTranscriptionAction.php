<?php

namespace Domain\Transcription\Actions;

use Domain\Transcription\Enums\TranscriptionStatus;
use Domain\Transcription\Events\TranscriptionRequested;
use Domain\Transcription\Exceptions\RetryNotAllowed;
use Domain\Transcription\Models\Transcription;
use Illuminate\Support\Facades\DB;

final class RetryTranscriptionAction
{
    public function execute(Transcription $record): Transcription
    {
        return DB::transaction(function () use ($record): Transcription {
            $changed = Transcription::whereKey($record->id)
                ->where('status', TranscriptionStatus::Failed->value)
                ->update(['status' => TranscriptionStatus::Queued->value, 'error' => null]);
            if ($changed !== 1) {
                throw new RetryNotAllowed('Only failed transcriptions can be retried.');
            }
            event(new TranscriptionRequested($record->id));

            return $record->refresh();
        });
    }
}
