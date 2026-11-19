<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTranscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['audio' => ['required', 'file', 'mimes:mp3,wav,m4a,flac,ogg',
            'extensions:mp3,wav,m4a,flac,ogg', 'max:102400']];
    }
}
