<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Concerns;

use Illuminate\Database\Eloquent\Model;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;

/**
 * Modelga media biriktirishni soddalashtiradi va `attached=true` ni avtomatik boshqaradi.
 *
 * - FK ustunlar: `$mediaColumns` ro'yxatidagi ustunlar (masalan `cover_media_id`) model
 *   saqlanganda ulardagi media avtomatik `attached=true` bo'ladi.
 * - Pivot: `$this->syncMedia('photos', $ids)` — per-model pivot relation'ini sync qiladi va belgilaydi.
 * - O'chirish: model o'chganda `$mediaColumns` va `$mediaRelations` dagi media ham o'chadi
 *   (fayl faqat transaction commit bo'lgandan keyin diskdan o'chiriladi).
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

    /**
     * Model o'chganda tozalanadigan pivot (BelongsToMany) media relation nomlari.
     * `protected function mediaRelations(): array { return ['photos']; }`
     *
     * @return list<string>
     */
    protected function mediaRelations(): array
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

        static::deleting(function (Model $model): void {
            /** @var self $model */
            $model->purgeMedia();
        });
    }

    /**
     * Modelga biriktirilgan barcha media'ni o'chiradi: FK ustunlar + pivot relation'lar.
     * Pivot bog'lanishlar uziladi; fayllar transaction commit bo'lgach diskdan o'chadi.
     */
    public function purgeMedia(): void
    {
        $service = app(MediaService::class);

        // Pivot relation'lar — media'ni o'chiramiz va bog'lanishni uzamiz.
        foreach ($this->mediaRelations() as $relation) {
            $media = $this->{$relation}()->get();
            $this->{$relation}()->detach();
            $media->each(fn (Media $item) => $service->delete($item));
        }

        // FK ustunlardagi media.
        $ids = array_values(array_filter(array_map(
            fn (string $column) => $this->getAttribute($column),
            $this->mediaColumns(),
        )));

        if ($ids !== []) {
            Media::query()->whereIn('id', $ids)->get()
                ->each(fn (Media $item) => $service->delete($item));
        }
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
