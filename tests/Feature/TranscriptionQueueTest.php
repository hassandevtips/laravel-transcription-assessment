<?php

namespace Tests\Feature;

use Domain\Transcription\Actions\CreateTranscriptionAction;
use Domain\Transcription\Contracts\AudioTranscriber;
use Domain\Transcription\DataTransferObjects\CreateTranscriptionData;
use Domain\Transcription\Events\TranscriptionRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TranscriptionQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_request_rolls_back_record_and_queued_job_and_removes_audio(): void
    {
        config(['queue.default' => 'database', 'transcription.token' => 'test-secret']);
        Storage::fake('local');
        Event::listen(TranscriptionRequested::class, function (): void {
            throw new \RuntimeException('Enqueue failure');
        });
        $this->withToken('test-secret')->postJson('/api/transcriptions', [
            'audio' => UploadedFile::fake()->create('voice.wav', 10, 'audio/wav'),
        ])->assertStatus(500);
        $this->assertDatabaseCount('transcriptions', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('audio'));
    }

    public function test_real_queue_fails_after_three_processing_exceptions(): void
    {
        config(['queue.default' => 'database']);
        $transcriber = $this->createMock(AudioTranscriber::class);
        $transcriber->expects($this->exactly(3))->method('transcribe')
            ->willThrowException(new \RuntimeException('temporary issue'));
        $this->app->instance(AudioTranscriber::class, $transcriber);
        $record = app(CreateTranscriptionAction::class)->execute(new CreateTranscriptionData('audio/test.wav', 'mock'));
        for ($attempt = 0; $attempt < 3; $attempt++) {
            DB::table('jobs')->update(['available_at' => time() - 1]);
            Artisan::call('queue:work', ['--once' => true, '--queue' => 'transcriptions', '--sleep' => 0]);
        }
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $this->assertSame('failed', $record->fresh()->status->value);
    }

    public function test_oversized_audio_is_rejected(): void
    {
        config(['transcription.token' => 'test-secret']);
        $this->withToken('test-secret')->postJson('/api/transcriptions', [
            'audio' => UploadedFile::fake()->create('large.wav', 102401, 'audio/wav'),
        ])->assertUnprocessable()->assertJsonValidationErrors('audio');
    }
}
