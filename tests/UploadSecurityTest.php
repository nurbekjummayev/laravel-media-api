<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NurbekJummayev\LaravelMediaApi\Models\Media;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;

/*
|--------------------------------------------------------------------------
| File upload — attack surface
|--------------------------------------------------------------------------
| MIME spoofing, qo'sh-kengaytma, content-da skript, path traversal,
| header injection va public-disk stored-XSS hujumlarini tekshiradi.
*/

function assertNoMediaStored(): void
{
    expect(Media::count())->toBe(0)
        ->and(Storage::disk('media')->allFiles())->toBeEmpty()
        ->and(Storage::disk('media_public')->allFiles())->toBeEmpty();
}

/**
 * Haqiqiy kontentli UploadedFile — fake fayllar MIME'ni nomdan aniqlaydi,
 * shuning uchun content-sniffing/spoofing testlari uchun real fayl kerak.
 */
function rawUpload(string $name, string $content): UploadedFile
{
    $tmp = tempnam(sys_get_temp_dir(), 'mediasec');
    file_put_contents($tmp, $content);

    return new UploadedFile($tmp, $name, null, null, true);
}

it('rejects a PHP payload disguised as a .jpg (real content sniffed)', function (): void {
    $this->post('/api/v1/media', [
        'files' => [rawUpload('avatar.jpg', '<?php phpinfo(); ?>')],
    ], ['Accept' => 'application/json'])->assertStatus(422);

    assertNoMediaStored();
});

it('rejects an HTML/JS payload disguised as a .png (real content sniffed)', function (): void {
    $this->post('/api/v1/media', [
        'files' => [rawUpload('pic.png', '<!DOCTYPE html><html><script>alert(1)</script></html>')],
    ], ['Accept' => 'application/json'])->assertStatus(422);

    assertNoMediaStored();
});

it('rejects a double-extension shell upload (shell.php.jpg)', function (): void {
    $this->postJson('/api/v1/media', [
        'files' => [UploadedFile::fake()->image('shell.php.jpg')],
    ])->assertStatus(422);

    assertNoMediaStored();
});

it('rejects a .phtml upload', function (): void {
    $this->postJson('/api/v1/media', [
        'files' => [UploadedFile::fake()->createWithContent('x.phtml', '<?php echo 1; ?>')],
    ])->assertStatus(422);

    assertNoMediaStored();
});

it('rejects an .htaccess upload', function (): void {
    $this->postJson('/api/v1/media', [
        'files' => [UploadedFile::fake()->createWithContent('.htaccess', "AddType application/x-httpd-php .jpg\n")],
    ])->assertStatus(422);

    assertNoMediaStored();
});

it('rejects an executable (.exe) upload', function (): void {
    $this->postJson('/api/v1/media', [
        'files' => [UploadedFile::fake()->create('setup.exe', 4)],
    ])->assertStatus(422);

    assertNoMediaStored();
});

it('blocks a scriptable SVG on the PUBLIC disk', function (): void {
    $this->postJson('/api/v1/media', [
        'type' => 'public',
        'files' => [UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')],
    ])->assertStatus(422);

    assertNoMediaStored();
});

it('allows an SVG on the PRIVATE disk but serves it sandboxed (no script execution)', function (): void {
    $media = app(MediaService::class)->store(
        UploadedFile::fake()->createWithContent('icon.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'private',
    );

    $this->get($media->url)
        ->assertOk()
        ->assertHeader('x-content-type-options', 'nosniff')
        ->assertHeader('content-security-policy');
});

it('throws and writes no file when store() is called directly with a blocked extension', function (): void {
    expect(fn () => app(MediaService::class)->store(UploadedFile::fake()->create('evil.phtml', 1)))
        ->toThrow(RuntimeException::class);

    expect(Storage::disk('media')->allFiles())->toBeEmpty();
});

it('throws and writes no file for an SVG on the public disk at the service layer', function (): void {
    expect(fn () => app(MediaService::class)->store(
        UploadedFile::fake()->createWithContent('icon.svg', '<svg></svg>'),
        'public',
    ))->toThrow(RuntimeException::class);

    expect(Storage::disk('media_public')->allFiles())->toBeEmpty();
});

it('never stores the file under the client-supplied name (uuid only, no path)', function (): void {
    $media = app(MediaService::class)->store(UploadedFile::fake()->image('My Photo.JPG'));

    expect($media->file)->toMatch('/^[0-9a-f-]{36}\.jpg$/')   // uuid + sanitised, lowercased ext
        ->and($media->file)->not->toContain('My Photo')
        ->and($media->fullPath())->not->toContain('..');
});

it('stores the content-detected mime, not a spoofable client value', function (): void {
    // .jpg deb nomlangan, lekin kontent aslida PNG (1x1).
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $media = app(MediaService::class)->store(rawUpload('a.jpg', $png));

    // Diskdagi nom .jpg (ruxsat etilgan), lekin saqlangan MIME haqiqiy kontentники.
    expect($media->mime)->toBe('image/png')
        ->and($media->ext)->toBe('jpg');
});
