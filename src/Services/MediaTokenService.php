<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Services;

use Illuminate\Support\Facades\Crypt;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use Throwable;

class MediaTokenService
{
    /**
     * Media uchun vaqtinchalik (shifrlangan) token yaratadi.
     */
    public function make(Media $media, ?int $ttlMinutes = null): string
    {
        $ttl = $ttlMinutes ?? (int) config('media.token_ttl', 60);

        return Crypt::encryptString(json_encode([
            'u' => $media->uuid,
            'e' => now()->addMinutes($ttl)->getTimestamp(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Token berilgan uuid uchun haqiqiy va muddati o'tmaganini tekshiradi.
     */
    public function valid(string $token, string $uuid): bool
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return ($payload['u'] ?? null) === $uuid
            && (int) ($payload['e'] ?? 0) >= now()->getTimestamp();
    }
}
