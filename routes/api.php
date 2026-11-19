<?php

use App\Http\Controllers\TranscriptionController;
use App\Http\Middlewares\AuthenticateServiceToken;
use Illuminate\Support\Facades\Route;

Route::middleware([AuthenticateServiceToken::class, 'throttle:transcriptions'])->group(function (): void {
    Route::post('/transcriptions', [TranscriptionController::class, 'store'])->middleware('throttle:audio-uploads');
    Route::get('/transcriptions/{transcription}', [TranscriptionController::class, 'show']);
    Route::post('/transcriptions/{transcription}/retry', [TranscriptionController::class, 'retry'])->middleware('throttle:audio-uploads');
});
