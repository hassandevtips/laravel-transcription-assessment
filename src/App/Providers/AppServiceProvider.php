<?php

namespace App\Providers;

use App\Listeners\QueueTranscription;
use Domain\Transcription\Contracts\AudioTranscriber;
use Domain\Transcription\Events\TranscriptionRequested;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Support\Transcription\PythonAudioTranscriber;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AudioTranscriber::class, PythonAudioTranscriber::class);
    }

    public function boot(): void
    {
        Event::listen(TranscriptionRequested::class, QueueTranscription::class);
        RateLimiter::for('transcriptions', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
        RateLimiter::for('audio-uploads', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
    }
}
