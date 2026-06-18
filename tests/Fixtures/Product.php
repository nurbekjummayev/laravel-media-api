<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use NurbekJummayev\LaravelMediaApi\Concerns\InteractsWithMedia;
use NurbekJummayev\LaravelMediaApi\Models\Media;

/**
 * Test fixture — InteractsWithMedia trait'ini tekshirish uchun.
 *
 * @property int|null $cover_media_id
 */
class Product extends Model
{
    use InteractsWithMedia;

    protected $table = 'products';

    protected $guarded = [];

    /**
     * @return list<string>
     */
    protected function mediaColumns(): array
    {
        return ['cover_media_id'];
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_media', 'product_id', 'media_id');
    }
}
