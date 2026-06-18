<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use RuntimeException;
use Throwable;

class MediaService
{
    /**
     * Faylni saqlaydi va `attached=false` Media yozuvini qaytaradi.
     *
     * Atomik: agar fayl diskka yozilmasa yoki Media yozuvi saqlanmasa, diskda
     * orphan fayl qolmaydi. Joriy DB transaction rollback bo'lsa (masalan,
     * response qaytguncha boshqa joyda xato bo'lsa) yozilgan fayl o'chiriladi.
     */
    public function store(UploadedFile $file, string $type = 'private', int|string|null $ownerId = null): Media
    {
        $disk = $type === 'public'
            ? (string) config('media.public_disk')
            : (string) config('media.disk');

        // 0. Kengaytmani diskka yozishdan OLDIN tasdiqlaymiz — bu metod FormRequest'siz
        //    to'g'ridan-to'g'ri chaqirilsa ham `.php`/`.svg` kabi xavfli yoki ruxsatsiz
        //    kengaytmali fayl umuman diskka tushmaydi.
        $ext = $this->safeExtension($file, $type);
        $uuid = (string) Str::uuid();
        $storedName = $uuid.'.'.$ext; // nom har doim UUID — mijoz yo'li/nomi ishlatilmaydi
        $path = now()->format('Y/m/d');
        $fullPath = $path.'/'.$storedName;

        // 1. Faylni diskka yozamiz va muvaffaqiyatini tekshiramiz
        //    (disklarda `throw => false`, shuning uchun natijani qo'lda tekshiramiz).
        if ($file->storeAs($path, $storedName, ['disk' => $disk]) === false) {
            throw new RuntimeException("Media faylni '{$disk}' diskka yozib bo'lmadi.");
        }

        // 2. Joriy transaction rollback bo'lsa, yozilgan faylni tozalaymiz
        //    (transaction bo'lmasa — no-op).
        DB::afterRollback(static function () use ($disk, $fullPath): void {
            Storage::disk($disk)->delete($fullPath);
        });

        try {
            // 3. DB yozuvi.
            return Media::query()->create([
                'uuid' => $uuid,
                'disk' => $disk,
                'path' => $path,
                'file' => $storedName,
                'name' => $this->safeName($file->getClientOriginalName()),
                'ext' => $ext,
                'mime' => $this->detectMime($file),
                'size' => $file->getSize() ?: 0,
                'hash' => @hash_file('sha256', $file->getRealPath()) ?: null,
                'type' => $type,
                'owner_id' => $ownerId,
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'attached' => false,
            ]);
        } catch (Throwable $e) {
            // Model saqlanmasa, hozirgina yozilgan faylni darhol o'chiramiz.
            Storage::disk($disk)->delete($fullPath);

            throw $e;
        }
    }

    /**
     * Diskka yoziladigan xavfsiz kengaytmani aniqlaydi.
     *
     * - faqat `[a-z0-9]` belgilar (path-traversal / maxsus belgilarni yo'qotadi);
     * - `blocked_extensions` da bo'lmasligi kerak (php, phtml, exe, sh, html, svg...);
     * - `allowed_extensions` bo'sh bo'lmasa — faqat o'sha ro'yxatdagilar.
     */
    private function safeExtension(UploadedFile $file, string $type): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $ext = (string) preg_replace('/[^a-z0-9]/', '', $ext);

        $blocked = array_map('strtolower', (array) config('media.blocked_extensions', []));
        $allowed = array_map('strtolower', (array) config('media.allowed_extensions', []));
        $publicBlocked = array_map('strtolower', (array) config('media.public_blocked_extensions', []));

        $isInvalid = $ext === ''
            || in_array($ext, $blocked, true)
            || ($allowed !== [] && ! in_array($ext, $allowed, true))
            || ($type === 'public' && in_array($ext, $publicBlocked, true));

        if ($isInvalid) {
            throw new RuntimeException("Ruxsat etilmagan yoki xavfli fayl kengaytmasi: '{$ext}'.");
        }

        return $ext;
    }

    /**
     * Saqlanadigan asl nomni tozalaydi: katalog qismlari va boshqaruv belgilarini
     * (jumladan CR/LF — Content-Disposition header injection) olib tashlaydi.
     */
    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));          // path traversal
        $name = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $name); // null/control/CRLF

        return $name === '' ? 'file' : Str::limit($name, 255, '');
    }

    /**
     * Faylning HAQIQIY MIME turini (finfo orqali) aniqlaydi — mijoz yuborgan,
     * soxtalashtirilishi mumkin bo'lgan `Content-Type` ga ishonmaydi.
     */
    private function detectMime(UploadedFile $file): ?string
    {
        try {
            return $file->getMimeType() ?: null;
        } catch (Throwable) {
            return null;
        }
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
     * Media'ni bazadan o'chiradi (soft delete) va faylni diskdan o'chiradi.
     *
     * Fayl faqat joriy DB transaction commit bo'lgandan keyin o'chiriladi —
     * transaction yo'q bo'lsa darhol. Shu sababli transaction rollback bo'lsa
     * (model o'chmay qolsa) fayl ham diskda saqlanib qoladi.
     */
    public function delete(Media $media): void
    {
        $disk = $media->disk;
        $path = $media->fullPath();

        $media->delete();

        DB::afterCommit(static function () use ($disk, $path): void {
            Storage::disk($disk)->delete($path);
        });
    }
}
