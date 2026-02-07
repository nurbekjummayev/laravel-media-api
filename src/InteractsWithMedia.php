<?php

namespace Nurbekjummayev\MediaApiLibrary;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Nurbekjummayev\MediaApiLibrary\Models\TempUpload;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\InteractsWithMedia as SpatieInteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin Model
 * @mixin HasMedia
 */
trait InteractsWithMedia
{
    use SpatieInteractsWithMedia;

    protected static bool $mediaRelationsRegistered = false;

    /**
     * @return array<string, array<string, mixed>|string>
     */
    abstract public function getMediaIds(): array;

    public static function bootInteractsWithMedia(): void
    {
        static::registerMediaRelations();

        static::created(function ($model) {
            $model->attachTempMedia();
        });
    }

    public function initializeInteractsWithMedia(): void
    {
        $this->registerTempMediaCollections();
    }

    protected static function registerMediaRelations(): void
    {
        if (static::$mediaRelationsRegistered) {
            return;
        }

        $instance = new static;
        $mediaIds = $instance->getMediaIds();

        if (empty($mediaIds)) {
            return;
        }

        foreach ($mediaIds as $collection => $config) {
            $config = self::normalizeMediaConfig($config);

            if ($config['multiple']) {
                static::resolveRelationUsing($collection, function ($model) use ($collection): MorphMany {
                    return $model->morphMany(Media::class, 'model')
                        ->where('collection_name', $collection)
                        ->orderBy('order_column');
                });
            } else {
                static::resolveRelationUsing($collection, function ($model) use ($collection): MorphOne {
                    return $model->morphOne(Media::class, 'model')
                        ->where('collection_name', $collection)
                        ->orderBy('order_column')
                        ->latestOfMany('order_column');
                });
            }
        }

        static::$mediaRelationsRegistered = true;
    }

    /**
     * @param  array<string, mixed>|string  $config
     * @return array<string, mixed>
     */
    protected static function normalizeMediaConfig(array|string $config): array
    {
        $defaults = [
            'field' => null,
            'multiple' => false,
            'accept' => [],
            'max_size' => null,
            'max_files' => null,
            'conversions' => [],
            'disk' => 'public',
        ];

        if (is_string($config)) {
            return array_merge($defaults, [
                'field' => $config,
                'multiple' => str_ends_with($config, '_ids'),
            ]);
        }

        $normalized = array_merge($defaults, $config);

        if (!isset($config['multiple']) && isset($config['field'])) {
            $normalized['multiple'] = str_ends_with($config['field'], '_ids');
        }

        return $normalized;
    }

    protected function registerTempMediaCollections(): void
    {
        $mediaIds = $this->getMediaIds();

        if (empty($mediaIds)) {
            return;
        }

        foreach ($mediaIds as $collectionName => $config) {
            $config = self::normalizeMediaConfig($config);

            $collection = $this->addMediaCollection($collectionName);

            if (!$config['multiple']) {
                $collection->singleFile();
            }

            if (!empty($config['accept'])) {
                $collection->acceptsMimeTypes($config['accept']);
            }

            if (!empty($config['max_size'])) {
                $maxSizeInBytes = $config['max_size'] * 1024 * 1024;
                $collection->withResponsiveImages()->onlyKeepLatest($config['max_files'] ?? 1000);
            }

            if (!empty($config['max_files']) && $config['multiple']) {
                $collection->onlyKeepLatest($config['max_files']);
            }

            if (!empty($config['conversions'])) {
                $this->registerMediaConversionsForCollection($collectionName, $config['conversions']);
            }
        }
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $mediaIds = $this->getMediaIds();

        foreach ($mediaIds as $collectionName => $config) {
            $config = self::normalizeMediaConfig($config);

            if (empty($config['conversions'])) {
                continue;
            }

            foreach ($config['conversions'] as $conversionName => $conversionConfig) {
                $conversion = $this->addMediaConversion($conversionName)
                    ->performOnCollections($collectionName);

                if (is_array($conversionConfig)) {
                    $width = $conversionConfig['width'] ?? $conversionConfig[0] ?? null;
                    $height = $conversionConfig['height'] ?? $conversionConfig[1] ?? null;
                    $fit = $conversionConfig['fit'] ?? Fit::Contain;

                    if ($width && $height) {
                        $conversion->fit($fit, $width, $height);
                    } elseif ($width) {
                        $conversion->width($width);
                    } elseif ($height) {
                        $conversion->height($height);
                    }

                    if (!empty($conversionConfig['format'])) {
                        $conversion->format($conversionConfig['format']);
                    }

                    if (!empty($conversionConfig['quality'])) {
                        $conversion->quality($conversionConfig['quality']);
                    }

                    if (!empty($conversionConfig['queued'])) {
                        $conversion->queued();
                    } else {
                        $conversion->nonQueued();
                    }
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $conversions
     */
    protected function registerMediaConversionsForCollection(string $collectionName, array $conversions): void
    {
        // Conversions are registered in registerMediaConversions() method
    }

    public function attachTempMedia(): void
    {
        $mediaIds = $this->getMediaIds();

        if (empty($mediaIds)) {
            return;
        }

        DB::transaction(function () use ($mediaIds) {
            foreach ($mediaIds as $collection => $config) {
                $config = self::normalizeMediaConfig($config);
                $ids = $this->getMediaIdsFromRequest($config['field']);

                if (empty($ids)) {
                    continue;
                }

                $this->attachMediaFromTempUploads($ids, $collection, $config);
            }
        });
    }

    /**
     * @return array<string>
     */
    protected function getMediaIdsFromRequest(string $field): array
    {
        $value = request()->input($field);

        if (empty($value)) {
            return [];
        }

        return Arr::wrap($value);
    }

    /**
     * @param  array<string>  $tempUploadIds
     * @param  array<string, mixed>  $config
     */
    protected function attachMediaFromTempUploads(array $tempUploadIds, string $collection, array $config = []): void
    {
        $tempUploads = TempUpload::query()
            ->whereIn('id', $tempUploadIds)
            ->where('is_attached', false)
            ->get();

        $maxFiles = $config['max_files'] ?? null;
        $attached = 0;

        foreach ($tempUploads as $tempUpload) {
            if ($maxFiles && $attached >= $maxFiles) {
                break;
            }

            if (!$tempUpload->fileExists()) {
                continue;
            }

            if (!empty($config['max_size'])) {
                $maxSizeBytes = $config['max_size'] * 1024 * 1024;
                if ($tempUpload->size > $maxSizeBytes) {
                    continue;
                }
            }

            if (!empty($config['accept'])) {
                if (
                    !$this->
                        mimeTypeMatches($tempUpload->mime_type, $config['accept'])
                ) {
                    continue;
                }
            }

            $disk = config("media-upload.disks.{$config['disk']}", $config['disk']);

            $this->addMedia($tempUpload->full_path)
                ->withCustomProperties([
                    'folder_path' => $tempUpload->getFolderPath(),
                ])
                ->toMediaCollection($collection, $disk);

            $tempUpload->markAsAttached();
            $tempUpload->deleteFile();
            $attached++;
        }
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
