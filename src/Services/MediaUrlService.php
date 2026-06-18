<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use NurbekJummayev\LaravelMediaApi\Models\Media;

/**
 * Media uchun vaqtinchalik imzolangan (signed) URL'larni yaratadi va tekshiradi.
 * Imzolash mantig'i shu yagona joyda — model URL yasashda, controller esa
 * tekshirishda shu servisga murojaat qiladi.
 */
class MediaUrlService
{
    /**
     * Imzolangan inline ko'rish URL'i.
     */
    public function viewUrl(Media $media, ?int $ttlMinutes = null): string
    {
        return $this->signedRoute('media.view', $media, $ttlMinutes);
    }

    /**
     * Imzolangan yuklab olish (download) URL'i.
     */
    public function downloadUrl(Media $media, ?int $ttlMinutes = null): string
    {
        return $this->signedRoute('media.download', $media, $ttlMinutes);
    }

    /**
     * Imzolangan so'rovni tekshiradi va mos media'ni qaytaradi.
     * Imzo avval tekshiriladi — bu media mavjudligini oshkor qilmaydi.
     */
    public function resolveSigned(Request $request, string $uuid): Media
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired signature.');

        return Media::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function signedRoute(string $route, Media $media, ?int $ttlMinutes): string
    {
        $ttl = $ttlMinutes ?? (int) config('media.url_ttl', 60);

        return URL::temporarySignedRoute($route, now()->addMinutes($ttl), ['uuid' => $media->uuid]);
    }
}
