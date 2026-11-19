<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class TranscriptionResource extends JsonResource
{
    public static $wrap = null;

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'status' => $this->status->value,
            'driver' => $this->driver, 'text' => $this->text ?? '',
            'language' => $this->language, 'duration' => $this->duration,
            'segments' => $this->segments ?? [], 'error' => $this->error,
        ];
    }
}
