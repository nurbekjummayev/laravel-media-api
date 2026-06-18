<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;
use NurbekJummayev\LaravelMediaApi\Services\MediaUrlService;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->urls = app(MediaUrlService::class);
    $this->media = app(MediaService::class)->store(UploadedFile::fake()->image('a.jpg'));
});

it('builds signed view and download urls', function (): void {
    expect($this->urls->viewUrl($this->media))
        ->toContain('/media/'.$this->media->uuid.'/view')
        ->toContain('signature=')
        ->and($this->urls->downloadUrl($this->media))
        ->toContain('/media/'.$this->media->uuid.'/download')
        ->toContain('signature=');
});

it('resolves media from a validly signed request', function (): void {
    $request = Request::create($this->urls->viewUrl($this->media));

    $resolved = $this->urls->resolveSigned($request, $this->media->uuid);

    expect($resolved->id)->toBe($this->media->id);
});

it('aborts with 403 when the signature is invalid', function (): void {
    $request = Request::create("/api/v1/media/{$this->media->uuid}/view?signature=bad&expires=9999999999");

    $this->urls->resolveSigned($request, $this->media->uuid);
})->throws(HttpException::class);

it('aborts with 404 for a correctly signed but unknown uuid', function (): void {
    $request = Request::create($this->urls->viewUrl($this->media));

    // Imzo to'g'ri, lekin boshqa uuid so'ralmoqda → topilmadi.
    $this->urls->resolveSigned($request, 'does-not-exist');
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);
