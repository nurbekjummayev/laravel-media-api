<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Concerns;

use Illuminate\Database\Eloquent\Model;
use NurbekJummayev\LaravelMediaApi\Models\Media;

/**
 * Modelga media biriktirishni soddalashtiradi va `attached=true` ni avtomatik boshqaradi.
 *
 * - FK ustunlar: `$mediaColumns` ro'yxatidagi ustunlar (masalan `cover_media_id`) model
 *   saqlanganda ulardagi media avtomatik `attached=true` bo'ladi.
 * - Pivot: `$this->syncMedia('photos', $ids)` — per-model pivot relation'ini sync qiladi va belgilaydi.
 */
trait InteractsWithMedia
{
    /**
     * Modeldagi media FK ustunlari. Model o'zida override qiladi, masalan:
     * `protected function mediaColumns(): array { return ['cover_media_id']; }`
     *
     * @return list<string>
     */
    protected function mediaColumns(): array
    {
        return [];
    }

    public static function bootInteractsWithMedia(): void
    {
        static::saved(function (Model $model): void {
            /** @var self $model */
            $ids = array_values(array_filter(array_map(
                fn (string $column) => $model->getAttribute($column),
                $model->mediaColumns(),
            )));

            $model->markMediaAttached($ids);
        });
    }

    /**
     * Per-model pivot relation'ini media id'lariga sync qiladi va `attached=true` qiladi.
     *
     * @param  array<int, int|string>  $mediaIds
     */
    public function syncMedia(string $relation, array $mediaIds): void
    {
        $this->{$relation}()->sync($mediaIds);

        $this->markMediaAttached($mediaIds);
    }

    /**
     * Berilgan media id'larini `attached=true` qiladi (musur tozalashdan saqlaydi).
     *
     * @param  array<int, int|string>  $mediaIds
     */
    public function markMediaAttached(array $mediaIds): void
    {
        if ($mediaIds === []) {
            return;
        }

        Media::query()->whereIn('id', $mediaIds)->where('attached', false)->update(['attached' => true]);
    }
}
