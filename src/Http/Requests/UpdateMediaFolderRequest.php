<?php

namespace Nurbekjummayev\MediaApiLibrary\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMediaFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('media_folders')->where(function ($query) {
                    return $query->where('parent_id', $this->input('parent_id', $this->route('folder')->parent_id));
                })->ignore($this->route('folder')->id),
            ],
            'parent_id' => ['nullable', 'uuid', 'exists:media_folders,id'],
            'disk' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The folder name is required.',
            'name.unique' => 'A folder with this name already exists in this location.',
            'parent_id.uuid' => 'The parent ID must be a valid UUID.',
            'parent_id.exists' => 'The selected parent folder does not exist.',
        ];
    }
}
