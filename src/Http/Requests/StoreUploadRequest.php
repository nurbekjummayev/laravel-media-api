<?php

namespace Local\MediaLibrary\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:102400'],
            'folder_id' => ['nullable', 'uuid', 'exists:media_folders,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'A file is required.',
            'file.file' => 'The uploaded item must be a valid file.',
            'file.max' => 'The file may not be larger than 100MB.',
            'folder_id.uuid' => 'The folder ID must be a valid UUID.',
            'folder_id.exists' => 'The selected folder does not exist.',
        ];
    }
}
