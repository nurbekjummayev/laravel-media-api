<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;

it('auto-generates a uuid on create when none is given', function (): void {
    $media = Media::query()->create([
        'disk' => 'media', 'path' => '2026/01/01', 'file' => 'x.jpg',
        'name' => 'x.jpg', 'ext' => 'jpg', 'size' => 1, 'type' => 'private',
    ]);

    expect($media->uuid)->not->toBeEmpty();
});

it('builds the full disk path from path + file', function (): void {
    $media = new Media(['path' => '/2026/06/18/', 'file' => 'name.jpg']);

    expect($media->fullPath())->toBe('2026/06/18/name.jpg');
});

it('exposes a temporary signed view url via the url attribute', function (): void {
    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.jpg'));

    $url = $media->url;

    expect($url)->toContain('/media/'.$media->uuid.'/view')
        ->and($url)->toContain('signature=')
        ->and($url)->toContain('expires=')
        ->and($media->toArray())->toHaveKey('url');
});

it('exposes a temporary signed download url', function (): void {
    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.jpg'));

    expect($media->downloadUrl())
        ->toContain('/media/'.$media->uuid.'/download')
        ->toContain('signature=');
});

it('reports whether the file exists on disk', function (): void {
    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.jpg'));

    expect($media->existsOnDisk())->toBeTrue();
});

it('filters with attached and unattached scopes', function (): void {
    $attached = app(MediaService::class)->store(UploadedFile::fake()->image('a.jpg'));
    $attached->update(['attached' => true]);
    app(MediaService::class)->store(UploadedFile::fake()->image('b.jpg')); // unattached

    expect(Media::attached()->count())->toBe(1)
        ->and(Media::unattached()->count())->toBe(1);
});

it('casts attached to boolean and size to integer', function (): void {
    $media = app(MediaService::class)->store(UploadedFile::fake()->image('a.jpg'));

    expect($media->attached)->toBeBool()
        ->and($media->size)->toBeInt();
});
