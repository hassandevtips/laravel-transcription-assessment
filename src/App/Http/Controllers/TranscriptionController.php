<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTranscriptionRequest;
use App\Http\Resources\TranscriptionResource;
use Domain\Transcription\Actions\CreateTranscriptionAction;
use Domain\Transcription\Actions\RetryTranscriptionAction;
use Domain\Transcription\DataTransferObjects\CreateTranscriptionData;
use Domain\Transcription\Exceptions\RetryNotAllowed;
use Domain\Transcription\Models\Transcription;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class TranscriptionController extends Controller
{
    public function store(StoreTranscriptionRequest $request, CreateTranscriptionAction $action): JsonResponse
    {
        $path = $request->file('audio')->store('audio', 'local');
        abort_unless(is_string($path), 500, 'Unable to store audio.');
        try {
            $record = $action->execute(new CreateTranscriptionData($path, config('transcription.driver')));
        } catch (Throwable $error) {
            Storage::disk('local')->delete($path);
            throw $error;
        }

        return (new TranscriptionResource($record))->response()->setStatusCode(202);
    }

    public function show(Transcription $transcription): TranscriptionResource
    {
        return new TranscriptionResource($transcription);
    }

    public function retry(Transcription $transcription, RetryTranscriptionAction $action): JsonResponse
    {
        try {
            $record = $action->execute($transcription);
        } catch (RetryNotAllowed $error) {
            return response()->json(['message' => $error->getMessage()], 409);
        }

        return (new TranscriptionResource($record))->response()->setStatusCode(202);
    }
}
