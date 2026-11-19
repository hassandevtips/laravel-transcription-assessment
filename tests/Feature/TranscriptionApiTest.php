<?php

namespace Tests\Feature;

use App\Jobs\TranscribeAudio;
use Domain\Transcription\Enums\TranscriptionStatus;
use Domain\Transcription\Models\Transcription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['transcription.token' => 'test-secret']);
        Storage::fake('local');
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/transcriptions')->assertUnauthorized();
    }

    public function test_rejects_non_audio_uploads(): void
    {
        $this->withToken('test-secret')->postJson('/api/transcriptions', [
            'audio' => UploadedFile::fake()->create('notes.txt', 1, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('audio');
    }

    public function test_upload_is_private_and_queued(): void
    {
        Queue::fake();
        $response = $this->withToken('test-secret')->postJson('/api/transcriptions', [
            'audio' => UploadedFile::fake()->create('voice.wav', 10, 'audio/wav'),
        ])->assertAccepted()->assertJsonPath('status', 'queued');
        $record = Transcription::findOrFail($response->json('id'));
        Storage::disk('local')->assertExists($record->audio_path);
        Queue::assertPushed(TranscribeAudio::class, 1);
        $this->assertArrayNotHasKey('audio_path', $response->json());
        $this->withToken('test-secret')->getJson('/api/transcriptions/'.$record->id)
            ->assertOk()->assertJsonPath('segments', []);
    }

    public function test_unknown_id_returns_not_found(): void
    {
        $this->withToken('test-secret')->getJson('/api/transcriptions/missing')->assertNotFound();
    }

    public function test_only_failed_transcriptions_can_be_retried(): void
    {
        Queue::fake();
        $record = Transcription::create(['audio_path' => 'audio/test.wav', 'status' => 'processing']);
        $this->withToken('test-secret')->postJson('/api/transcriptions/'.$record->id.'/retry')->assertConflict();
        $record->update(['status' => 'failed', 'error' => 'Transcription failed. Please retry.']);
        $this->withToken('test-secret')->postJson('/api/transcriptions/'.$record->id.'/retry')
            ->assertAccepted()->assertJsonPath('status', 'queued')->assertJsonPath('error', null);
        $this->withToken('test-secret')->postJson('/api/transcriptions/'.$record->id.'/retry')->assertConflict();
        Queue::assertPushed(TranscribeAudio::class, 1);
    }

    public function test_mock_job_returns_segment_timestamps_and_is_idempotent(): void
    {
        config(['transcription.driver' => 'mock']);
        // A real two-second PCM WAV, without external test fixtures.
        $pcm = str_repeat("\x00", 16000 * 2 * 2);
        $wav = 'RIFF'.pack('V', 36 + strlen($pcm)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 16000, 32000, 2, 16).'data'.pack('V', strlen($pcm)).$pcm;
        Storage::disk('local')->put('audio/test.wav', $wav);
        $record = Transcription::create(['audio_path' => 'audio/test.wav', 'driver' => 'mock', 'status' => 'queued']);
        $job = new TranscribeAudio($record->id);
        app()->call([$job, 'handle']);
        $record->refresh();
        $this->assertSame(TranscriptionStatus::Completed, $record->status);
        $this->assertSame('mock', $record->driver);
        $this->assertSame(0, (int) $record->segments[0]['start']);
        $this->assertSame(2, (int) $record->segments[0]['end']);
        $this->assertStringContainsString('Mock', $record->text);
        app()->call([$job, 'handle']);
        $this->assertSame($record->text, $record->fresh()->text);
    }

    public function test_exhausted_job_marks_record_failed_without_exposing_internal_error(): void
    {
        $record = Transcription::create(['audio_path' => 'audio/test.wav', 'status' => 'processing']);
        (new TranscribeAudio($record->id))->failed(new \RuntimeException('private /server/path'));
        $this->withToken('test-secret')->getJson('/api/transcriptions/'.$record->id)
            ->assertOk()->assertJsonPath('status', 'failed')
            ->assertJsonPath('error', 'Transcription failed. Please retry.');
    }
}
