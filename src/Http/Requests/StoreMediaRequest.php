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
     * Xavfli kengaytmalarni qo'shimcha bloklash:
     *  - asl nomdagi BARCHA kengaytmalar (qo'sh-kengaytma: `shell.php.jpg`);
     *  - faylning HAQIQIY kontentidan topilgan kengaytma (nomi soxta bo'lsa ham);
     *  - public yuklamalar uchun `public_blocked_extensions` (SVG/XML kabi aktiv kontent).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $blocked = array_map('strtolower', config('media.blocked_extensions', []));

            $isPublic = $this->input('type') === 'public';
            if ($isPublic) {
                $blocked = array_merge($blocked, array_map('strtolower', config('media.public_blocked_extensions', [])));
            }

            foreach ((array) $this->file('files') as $i => $file) {
                if (! $file) {
                    continue;
                }

                $name = strtolower($file->getClientOriginalName());
                $parts = array_slice(explode('.', $name), 1); // barcha kengaytmalar (double-ext ham)

                // Kontentdan finfo orqali topilgan haqiqiy kengaytma (spoofing'ga qarshi).
                $guessed = strtolower((string) $file->guessExtension());
                if ($guessed !== '') {
                    $parts[] = $guessed;
                }

                if (array_intersect($parts, $blocked) !== []) {
                    $validator->errors()->add("files.{$i}", 'Bu fayl turi ruxsat etilmagan.');
                }
            }
        });
    }
}
