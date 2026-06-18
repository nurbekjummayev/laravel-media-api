<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
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
            'files' => ['required', 'array', 'min:1', 'max:'.(int) config('media.max_files_per_request', 20)],
            'files.*' => [
                'required',
                'file',
                'max:'.(int) config('media.max_size', 102400),
                'mimes:'.implode(',', config('media.allowed_extensions', [])),
            ],
            'type' => ['nullable', Rule::in(['public', 'private'])],
        ];
    }

    /**
     * Xavfli kengaytmalarni (php, exe, double-ext) qo'shimcha bloklash.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $blocked = array_map('strtolower', config('media.blocked_extensions', []));

            foreach ((array) $this->file('files') as $i => $file) {
                if (! $file) {
                    continue;
                }

                $name = strtolower($file->getClientOriginalName());
                $parts = array_slice(explode('.', $name), 1); // barcha kengaytmalar (double-ext ham)

                if (array_intersect($parts, $blocked) !== []) {
                    $validator->errors()->add("files.{$i}", 'Bu fayl turi ruxsat etilmagan.');
                }
            }
        });
    }
}
