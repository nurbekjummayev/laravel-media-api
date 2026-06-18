<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use NurbekJummayev\LaravelMediaApi\Http\Requests\StoreMediaRequest;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController
{
    public function __construct(
        private readonly MediaService $service,
    ) {}

    /**
     * POST {prefix}/media — bir yoki bir nechta fayl yuklash. `attached=false` qaytadi.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $type = (string) ($request->input('type') ?? 'private');
        $ownerId = Auth::id();

        // Hammasi-yoki-hech narsa: birorta fayl saqlanmasa, transaction rollback
        // bo'ladi va oldin yozilgan fayllar diskdan tozalanadi (afterRollback).
        $media = DB::transaction(fn (): array => array_map(
            fn ($file): Media => $this->service->store($file, $type, $ownerId),
            $request->file('files'),
        ));

        return createdResponse($media);
    }

    /**
     * GET {prefix}/media/{uuid}/view — faylni inline ko'rsatadi (signed URL bilan, auth'siz).
     */
    public function view(Request $request, string $uuid): StreamedResponse
    {
        $media = $this->authorizeSignature($request, $uuid);

        // SVG/HTML inline ochilganda <script> ishlamasligi uchun sandbox + nosniff.
        return Storage::disk($media->disk)->response($media->fullPath(), $media->name, [
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * GET {prefix}/media/{uuid}/download — faylni yuklab olish (attachment, signed URL bilan).
     */
    public function download(Request $request, string $uuid): StreamedResponse
    {
        $media = $this->authorizeSignature($request, $uuid);

        return Storage::disk($media->disk)->download($media->fullPath(), $media->name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * DELETE {prefix}/media/{id} — media'ni o'chirish.
     */
    public function destroy(int $id): JsonResponse
    {
        $media = Media::query()->findOrFail($id);
        $this->service->delete($media);

        return okResponse();
    }

    private function authorizeSignature(Request $request, string $uuid): Media
    {
        // Imzo (signature) avval tekshiriladi — bu media mavjudligini oshkor qilmaydi.
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired signature.');

        return Media::query()->where('uuid', $uuid)->firstOrFail();
    }
}
