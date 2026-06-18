<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;

beforeEach(function (): void {
    $this->service = app(MediaService::class);
});

it('stores an uploaded file as an unattached media record', function (): void {
    $file = UploadedFile::fake()->image('avatar.png', 10, 10);

    $media = $this->service->store($file, 'private', 42);

    expect($media)->toBeInstanceOf(Media::class)
        ->and($media->exists)->toBeTrue()
        ->and($media->attached)->toBeFalse()
        ->and($media->type)->toBe('private')
        ->and($media->disk)->toBe('media')
        ->and($media->ext)->toBe('png')
        ->and($media->name)->toBe('avatar.png')
        ->and($media->owner_id)->toBe(42)
        ->and($media->uuid)->not->toBeEmpty()
        ->and($media->size)->toBeGreaterThan(0)
        ->and($media->hash)->not->toBeNull();

    Storage::disk('media')->assertExists($media->fullPath());
});

it('routes public uploads to the public disk', function (): void {
    $media = $this->service->store(UploadedFile::fake()->create('doc.pdf', 5), 'public');

    expect($media->type)->toBe('public')
        ->and($media->disk)->toBe('media_public');

    Storage::disk('media_public')->assertExists($media->fullPath());
});

it('stores the file under a dated Y/m/d path with the uuid as filename', function (): void {
    $media = $this->service->store(UploadedFile::fake()->image('a.jpg'));

    expect($media->path)->toBe(now()->format('Y/m/d'))
        ->and($media->file)->toBe($media->uuid.'.jpg');
});

it('marks the given media ids as attached', function (): void {
    $a = $this->service->store(UploadedFile::fake()->image('a.jpg'));
    $b = $this->service->store(UploadedFile::fake()->image('b.jpg'));

    $this->service->markAttached([$a->id, $b->id]);

    expect($a->fresh()->attached)->toBeTrue()
        ->and($b->fresh()->attached)->toBeTrue();
});

it('does nothing when marking an empty id list', function (): void {
    $a = $this->service->store(UploadedFile::fake()->image('a.jpg'));

    $this->service->markAttached([]);

    expect($a->fresh()->attached)->toBeFalse();
});

it('deletes the file from disk and soft-deletes the record', function (): void {
    $media = $this->service->store(UploadedFile::fake()->image('a.jpg'));
    $path = $media->fullPath();

    $this->service->delete($media);

    Storage::disk('media')->assertMissing($path);
    expect(Media::query()->find($media->id))->toBeNull()
        ->and(Media::withTrashed()->find($media->id))->not->toBeNull();
});
