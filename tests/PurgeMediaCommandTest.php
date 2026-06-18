<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;

beforeEach(function (): void {
    $this->service = app(MediaService::class);
});

it('purges unattached media older than the threshold from disk and db', function (): void {
    $orphan = $this->service->store(UploadedFile::fake()->image('old.jpg'));
    $orphan->forceFill(['created_at' => now()->subHours(48)])->save();
    $path = $orphan->fullPath();

    $this->artisan('media:purge', ['--hours' => 24])
        ->expectsOutputToContain('Purged 1 orphan media.')
        ->assertSuccessful();

    Storage::disk('media')->assertMissing($path);
    expect(Media::withTrashed()->find($orphan->id))->toBeNull();
});

it('keeps recent unattached media within the window', function (): void {
    $recent = $this->service->store(UploadedFile::fake()->image('new.jpg'));
    $recent->forceFill(['created_at' => now()->subHours(2)])->save();

    $this->artisan('media:purge', ['--hours' => 24])->assertSuccessful();

    expect(Media::find($recent->id))->not->toBeNull();
    Storage::disk('media')->assertExists($recent->fullPath());
});

it('never purges attached media regardless of age', function (): void {
    $attached = $this->service->store(UploadedFile::fake()->image('keep.jpg'));
    $attached->forceFill(['attached' => true, 'created_at' => now()->subHours(100)])->save();

    $this->artisan('media:purge', ['--hours' => 24])->assertSuccessful();

    expect(Media::find($attached->id))->not->toBeNull();
});

it('falls back to the configured purge window when no hours option is given', function (): void {
    config()->set('media.purge_after_hours', 24);
    $orphan = $this->service->store(UploadedFile::fake()->image('old.jpg'));
    $orphan->forceFill(['created_at' => now()->subHours(48)])->save();

    $this->artisan('media:purge')->assertSuccessful();

    expect(Media::withTrashed()->find($orphan->id))->toBeNull();
});
