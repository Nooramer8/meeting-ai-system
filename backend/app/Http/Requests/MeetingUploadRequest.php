<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MeetingUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'language' => ['required', 'string', 'in:auto,ar,en'],
            'meeting_file' => [
                'required',
                'file',
                'mimetypes:audio/mpeg,audio/mp3,audio/mp4,audio/x-m4a,audio/wav,audio/x-wav,audio/webm,audio/ogg,video/mp4,video/webm,video/quicktime',
                'max:102400',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'meeting_file.max' => 'The meeting file must be 100 MB or less. For longer meetings, compress or chunk the audio first.',
        ];
    }
}
