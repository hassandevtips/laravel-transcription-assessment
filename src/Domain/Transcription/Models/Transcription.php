<?php

namespace Domain\Transcription\Models;

use Domain\Transcription\Enums\TranscriptionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transcription extends Model
{
    use HasUuids;

    protected $fillable = ['audio_path', 'driver', 'status', 'text', 'segments', 'language', 'duration', 'error'];

    protected function casts(): array
    {
        return ['status' => TranscriptionStatus::class, 'segments' => 'array', 'duration' => 'float'];
    }
}
