<?php

return [
    'token' => env('TRANSCRIPTION_API_TOKEN', ''),
    'driver' => env('TRANSCRIPTION_DRIVER', 'whisper'),
    'python' => env('TRANSCRIPTION_PYTHON', 'python3'),
    'model' => env('TRANSCRIPTION_MODEL', 'base'),
    'ffmpeg' => env('TRANSCRIPTION_FFMPEG', 'ffmpeg'),
    'ffprobe' => env('TRANSCRIPTION_FFPROBE', 'ffprobe'),
];
