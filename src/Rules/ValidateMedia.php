<?php

namespace Local\MediaLibrary\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;
use Local\MediaLibrary\HasMedia;
use Local\MediaLibrary\Models\TempUpload;

class ValidateMedia implements ValidationRule
{
    /**
     * @param  class-string<HasMedia>  $modelClass
     */
    public function __construct(
        protected string $modelClass,
        protected string $collection
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (empty($value)) {
            return;
        }

        $ids = Arr::wrap($value);
        $config = $this->getCollectionConfig();

        if (empty($config)) {
            $fail("Media collection '{$this->collection}' is not configured.");

            return;
        }

        // Check max files
        if (! empty($config['max_files']) && count($ids) > $config['max_files']) {
            $fail("Maximum {$config['max_files']} files allowed for {$this->collection}.");

            return;
        }

        // Check single file
        if (! $config['multiple'] && count($ids) > 1) {
            $fail("Only one file allowed for {$this->collection}.");

            return;
        }

        $tempUploads = TempUpload::query()
            ->whereIn('id', $ids)
            ->where('is_attached', false)
            ->get();

        if ($tempUploads->count() !== count($ids)) {
            $fail('One or more files are invalid or already used.');

            return;
        }

        foreach ($tempUploads as $tempUpload) {
            // Check file exists
            if (! $tempUpload->fileExists()) {
                $fail("File '{$tempUpload->original_name}' not found.");

                return;
            }

            // Check max size
            if (! empty($config['max_size'])) {
                $maxBytes = $config['max_size'] * 1024 * 1024;
                if ($tempUpload->size > $maxBytes) {
                    $sizeMb = round($tempUpload->size / 1024 / 1024, 2);
                    $fail("File '{$tempUpload->original_name}' is too large ({$sizeMb}MB). Maximum size is {$config['max_size']}MB.");

                    return;
                }
            }

            // Check mime type
            if (! empty($config['accept'])) {
                if (! $this->mimeTypeMatches($tempUpload->mime_type, $config['accept'])) {
                    $fail("File '{$tempUpload->original_name}' has invalid type ({$tempUpload->mime_type}).");

                    return;
                }
            }
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getCollectionConfig(): ?array
    {
        $model = new $this->modelClass;

        if (! method_exists($model, 'getMediaIds')) {
            return null;
        }

        $mediaIds = $model->getMediaIds();

        if (! isset($mediaIds[$this->collection])) {
            return null;
        }

        return $this->normalizeConfig($mediaIds[$this->collection]);
    }

    /**
     * @param  array<string, mixed>|string  $config
     * @return array<string, mixed>
     */
    protected function normalizeConfig(array|string $config): array
    {
        $defaults = [
            'field' => null,
            'multiple' => false,
            'accept' => [],
            'max_size' => null,
            'max_files' => null,
        ];

        if (is_string($config)) {
            return array_merge($defaults, [
                'field' => $config,
                'multiple' => str_ends_with($config, '_ids'),
            ]);
        }

        $normalized = array_merge($defaults, $config);

        if (! isset($config['multiple']) && isset($config['field'])) {
            $normalized['multiple'] = str_ends_with($config['field'], '_ids');
        }

        return $normalized;
    }

    /**
     * @param  array<string>  $acceptedTypes
     */
    protected function mimeTypeMatches(string $mimeType, array $acceptedTypes): bool
    {
        foreach ($acceptedTypes as $accepted) {
            if ($accepted === $mimeType) {
                return true;
            }

            if (str_ends_with($accepted, '/*')) {
                $prefix = str_replace('/*', '/', $accepted);
                if (str_starts_with($mimeType, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
