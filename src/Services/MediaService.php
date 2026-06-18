<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NurbekJummayev\LaravelMediaApi\Models\Media;

class MediaService
{
    /**
     * Faylni saqlaydi va `attached=false` Media yozuvini qaytaradi.
     */
    public function store(UploadedFile $file, string $type = 'private', int|string|null $ownerId = null): Media
    {
        $disk = $type === 'public'
            ? (string) config('media.public_disk')
            : (string) config('media.disk');

        $ext = strtolower($file->getClientOriginalExtension());
        $uuid = (string) Str::uuid();
        $storedName = $uuid.($ext !== '' ? '.'.$ext : '');
        $path = now()->format('Y/m/d');

        $file->storeAs($path, $storedName, ['disk' => $disk]);

        return Media::query()->create([
            'uuid' => $uuid,
            'disk' => $disk,
            'path' => $path,
            'file' => $storedName,
            'name' => $file->getClientOriginalName(),
            'ext' => $ext,
            'mime' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'hash' => @hash_file('sha256', $file->getRealPath()) ?: null,
            'type' => $type,
            'owner_id' => $ownerId,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'attached' => false,
        ]);
    }

    /**
     * Berilgan media id'larni biriktirilgan deb belgilaydi (musur tozalashdan saqlaydi).
     *
     * @param  array<int, int|string>  $ids
     */
    public function markAttached(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        Media::query()->whereIn('id', $ids)->update(['attached' => true]);
    }

    /**
     * Media'ni diskdan va bazadan o'chiradi (soft delete).
     */
    public function delete(Media $media): void
    {
        Storage::disk($media->disk)->delete($media->fullPath());

        $media->delete();
    }
}
