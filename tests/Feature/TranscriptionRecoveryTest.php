<?php

namespace Tests\Feature;

use Domain\Transcription\Actions\TranscribeAudioAction;
use Domain\Transcription\Contracts\AudioTranscriber;
use Domain\Transcription\Enums\TranscriptionStatus;
use Domain\Transcription\Models\Transcription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscriptionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_transient_failure_is_retriable_and_not_completed(): void
    {
        $transcriber = $this->createMock(AudioTranscriber::class);
        $transcriber->method('transcribe')->willThrowException(new \RuntimeException('temporary issue'));
        $record = Transcription::create(['audio_path' => 'audio/test.wav', 'status' => 'queued']);
        try {
            (new TranscribeAudioAction($transcriber))->execute($record->id);
            $this->fail('The queue must receive the exception for automatic retry.');
        } catch (\RuntimeException $error) {
            $this->assertSame('temporary issue', $error->getMessage());
        }
        $this->assertSame(TranscriptionStatus::Retrying, $record->fresh()->status);
    }

    public function test_unconfigured_service_rejects_requests(): void
    {
        config(['transcription.token' => '']);
        $this->getJson('/api/transcriptions/missing')->assertStatus(503);
    }
}
