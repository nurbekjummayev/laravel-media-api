<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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

it('leaves no orphan file when the media record cannot be saved', function (): void {
    // Media yozuvini saqlashni majburan fail qilamiz.
    Schema::drop('media');

    try {
        $this->service->store(UploadedFile::fake()->image('a.jpg'));
    } catch (Throwable) {
        // kutilgan
    }

    // Diskka yozilgan fayl orphan bo'lib qolmasligi kerak.
    expect(Storage::disk('media')->allFiles())->toBeEmpty();
});

it('removes written files when the surrounding transaction rolls back', function (): void {
    $path = null;

    try {
        DB::transaction(function () use (&$path): void {
            $media = $this->service->store(UploadedFile::fake()->image('a.jpg'));
            $path = $media->fullPath();

            Storage::disk('media')->assertExists($path);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // kutilgan
    }

    Storage::disk('media')->assertMissing($path);
    expect(Media::count())->toBe(0);
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

it('defers the file removal until the transaction commits', function (): void {
    $media = $this->service->store(UploadedFile::fake()->image('a.jpg'));
    $path = $media->fullPath();

    DB::transaction(function () use ($media, $path): void {
        $this->service->delete($media);

        // Transaction hali ochiq — fayl hali diskda.
        Storage::disk('media')->assertExists($path);
    });

    // Commit bo'ldi — fayl o'chdi.
    Storage::disk('media')->assertMissing($path);
});

it('keeps the file when the transaction rolls back', function (): void {
    $media = $this->service->store(UploadedFile::fake()->image('a.jpg'));
    $path = $media->fullPath();

    try {
        DB::transaction(function () use ($media): void {
            $this->service->delete($media);
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // kutilgan
    }

    Storage::disk('media')->assertExists($path);
    expect(Media::query()->find($media->id))->not->toBeNull();
});
