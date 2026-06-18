<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use NurbekJummayev\LaravelMediaApi\Http\Requests\StoreMediaRequest;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;
use NurbekJummayev\LaravelMediaApi\Services\MediaTokenService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController
{
    public function __construct(
        private readonly MediaService $service,
        private readonly MediaTokenService $tokens,
    ) {}

    /**
     * POST {prefix}/media — bir yoki bir nechta fayl yuklash. `attached=false` qaytadi.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $type = (string) ($request->input('type') ?? 'private');
        $ownerId = Auth::id();

        $media = array_map(
            fn ($file): Media => $this->service->store($file, $type, $ownerId),
            $request->file('files'),
        );

        return createdResponse($media);
    }

    /**
     * GET {prefix}/media/{uuid}/view?token= — faylni inline ko'rsatadi (token bilan, auth'siz).
     */
    public function view(Request $request, string $uuid): StreamedResponse
    {
        $media = $this->authorizeToken($request, $uuid);

        return Storage::disk($media->disk)->response($media->fullPath(), $media->name);
    }

    /**
     * GET {prefix}/media/{uuid}/download?token= — faylni yuklab olish (attachment).
     */
    public function download(Request $request, string $uuid): StreamedResponse
    {
        $media = $this->authorizeToken($request, $uuid);

        return Storage::disk($media->disk)->download($media->fullPath(), $media->name);
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

    private function authorizeToken(Request $request, string $uuid): Media
    {
        $media = Media::query()->where('uuid', $uuid)->firstOrFail();

        abort_unless(
            $this->tokens->valid((string) $request->query('token'), $uuid),
            403,
            'Invalid or expired token.',
        );

        return $media;
    }
}
