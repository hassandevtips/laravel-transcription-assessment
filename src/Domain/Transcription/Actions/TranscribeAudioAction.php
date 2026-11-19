<?php

namespace Domain\Transcription\Actions;

use Domain\Transcription\Contracts\AudioTranscriber;
use Domain\Transcription\Enums\TranscriptionStatus;
use Domain\Transcription\Events\TranscriptionCompleted;
use Domain\Transcription\Models\Transcription;
use Throwable;

final class TranscribeAudioAction
{
    public function __construct(private AudioTranscriber $transcriber) {}

    public function execute(string $id): void
    {
        $record = Transcription::findOrFail($id);
        if (in_array($record->status, [TranscriptionStatus::Completed, TranscriptionStatus::Failed], true)) {
            return;
        }
        $record->update(['status' => TranscriptionStatus::Processing, 'error' => null]);
        try {
            $result = $this->transcriber->transcribe($record->audio_path, $record->driver);
        } catch (Throwable $error) {
            $record->update(['status' => TranscriptionStatus::Retrying]);
            throw $error;
        }
        $record->update([
            'status' => TranscriptionStatus::Completed,
            'text' => $result->text, 'driver' => $result->driver,
            'segments' => array_map(fn ($segment) => $segment->toArray(), $result->segments),
            'language' => $result->language, 'duration' => $result->duration, 'error' => null,
        ]);
        event(new TranscriptionCompleted($id));
    }
}
