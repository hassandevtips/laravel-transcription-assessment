<?php

namespace Tests\Feature;

use App\Jobs\TranscribeAudio;
use Domain\Transcription\Actions\RetryTranscriptionAction;
use Domain\Transcription\Contracts\AudioTranscriber;
use Domain\Transcription\DataTransferObjects\TranscriptData;
use Domain\Transcription\Models\Transcription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QueueLockRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_retry_survives_a_stale_worker_lock(): void
    {
        config(['queue.default' => 'database']);
        $transcriber = $this->createMock(AudioTranscriber::class);
        $transcriber->expects($this->once())->method('transcribe')->willReturn(TranscriptData::fromArray([
            'text' => 'Hello', 'driver' => 'mock', 'duration' => 1,
            'segments' => [['start' => 0, 'end' => 1, 'text' => 'Hello']],
        ]));
        $this->app->instance(AudioTranscriber::class, $transcriber);
        $record = Transcription::create(['audio_path' => 'audio/test.wav', 'driver' => 'mock', 'status' => 'failed']);
        $job = new TranscribeAudio($record->id);
        $middleware = new WithoutOverlapping('transcription:'.$record->id);
        $lock = Cache::lock($middleware->getLockKey($job), 3660);
        $this->assertTrue($lock->get());
        app(RetryTranscriptionAction::class)->execute($record);
        Artisan::call('queue:work', ['--once' => true, '--queue' => 'transcriptions', '--sleep' => 0]);
        $this->assertDatabaseCount('jobs', 1);
        $this->assertSame('queued', $record->fresh()->status->value);
        $lock->release();
        DB::table('jobs')->update(['available_at' => time() - 1]);
        Artisan::call('queue:work', ['--once' => true, '--queue' => 'transcriptions', '--sleep' => 0]);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertSame('completed', $record->fresh()->status->value);
    }
}
