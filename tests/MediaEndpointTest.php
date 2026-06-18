<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;

function uploadedMedia(string $name = 'a.jpg'): Media
{
    return app(MediaService::class)->store(UploadedFile::fake()->image($name));
}

it('uploads files and returns created (201) unattached media', function (): void {
    $response = $this->postJson('/api/v1/media', [
        'files' => [
            UploadedFile::fake()->image('one.jpg'),
            UploadedFile::fake()->image('two.png'),
        ],
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data');

    expect(Media::count())->toBe(2)
        ->and(Media::unattached()->count())->toBe(2);
});

it('rejects an upload with no files (422)', function (): void {
    $this->postJson('/api/v1/media', [])->assertStatus(422);
});

it('rejects a blocked double-extension file (422)', function (): void {
    $this->postJson('/api/v1/media', [
        'files' => [UploadedFile::fake()->image('shell.php.jpg')],
    ])->assertStatus(422);
});

it('streams a file inline for view with a valid signature (200)', function (): void {
    $media = uploadedMedia();

    $this->get($media->url)
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('content-security-policy');
});

it('downloads a file as attachment with a valid signature (200)', function (): void {
    $media = uploadedMedia();

    $this->get($media->downloadUrl())
        ->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename='.$media->name);
});

it('forbids view without a valid signature (403)', function (): void {
    $media = uploadedMedia();

    $this->get("/api/v1/media/{$media->uuid}/view")->assertForbidden();
    $this->get("/api/v1/media/{$media->uuid}/view?signature=bogus&expires=9999999999")->assertForbidden();
});

it('forbids view once the signed url has expired (403)', function (): void {
    $media = uploadedMedia();
    $url = $media->url;

    $this->travel(2)->hours();

    $this->get($url)->assertForbidden();
});

it('returns 404 for a correctly-signed but unknown uuid', function (): void {
    $url = URL::temporarySignedRoute('media.view', now()->addHour(), ['uuid' => 'does-not-exist']);

    $this->get($url)->assertNotFound();
});

it('deletes a media record and removes the file (200)', function (): void {
    $media = uploadedMedia();
    $path = $media->fullPath();

    $this->deleteJson("/api/v1/media/{$media->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    Storage::disk('media')->assertMissing($path);
    expect(Media::find($media->id))->toBeNull();
});

it('returns 404 when deleting an unknown id', function (): void {
    $this->deleteJson('/api/v1/media/999999')->assertNotFound();
});
