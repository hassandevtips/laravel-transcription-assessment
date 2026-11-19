<?php

namespace Support\Transcription;

use Domain\Transcription\Contracts\AudioTranscriber;
use Domain\Transcription\DataTransferObjects\TranscriptData;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

final class PythonAudioTranscriber implements AudioTranscriber
{
    public function transcribe(string $audioPath, string $driver): TranscriptData
    {
        $process = new Process([
            config('transcription.python'), base_path('scripts/transcribe.py'),
            '--input', Storage::disk('local')->path($audioPath),
            '--driver', $driver, '--model', config('transcription.model'),
            '--ffmpeg', config('transcription.ffmpeg'), '--ffprobe', config('transcription.ffprobe'),
        ]);
        $process->setTimeout(3500)->mustRun();
        $data = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);

        return TranscriptData::fromArray($data);
    }
}
