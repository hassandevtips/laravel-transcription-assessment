<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json([
    'service' => 'Laravel Transcription API',
    'documentation' => 'README.md',
    'health' => '/up',
]));
