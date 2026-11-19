<?php

namespace Domain\Transcription\Actions;

use Domain\Transcription\DataTransferObjects\CreateTranscriptionData;
use Domain\Transcription\Enums\TranscriptionStatus;
use Domain\Transcription\Events\TranscriptionRequested;
use Domain\Transcription\Models\Transcription;
use Illuminate\Support\Facades\DB;

final class CreateTranscriptionAction
{
    public function execute(CreateTranscriptionData $data): Transcription
    {
        return DB::transaction(function () use ($data): Transcription {
            $record = Transcription::create([
                'audio_path' => $data->audioPath, 'driver' => $data->driver,
                'status' => TranscriptionStatus::Queued, 'segments' => [],
            ]);
            event(new TranscriptionRequested($record->id));

            return $record;
        });
    }
}
