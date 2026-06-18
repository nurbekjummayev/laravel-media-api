<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;
use NurbekJummayev\LaravelMediaApi\Tests\Fixtures\Product;

beforeEach(function (): void {
    Schema::create('products', function ($table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->foreignId('cover_media_id')->nullable();
        $table->timestamps();
    });

    Schema::create('product_media', function ($table): void {
        $table->foreignId('product_id');
        $table->foreignId('media_id');
    });

    $this->service = app(MediaService::class);
});

it('auto-marks an fk media column as attached when the model is saved', function (): void {
    $cover = $this->service->store(UploadedFile::fake()->image('cover.jpg'));
    expect($cover->attached)->toBeFalse();

    Product::create(['name' => 'Phone', 'cover_media_id' => $cover->id]);

    expect($cover->fresh()->attached)->toBeTrue();
});

it('does not break saving when no media column is set', function (): void {
    $product = Product::create(['name' => 'No cover']);

    expect($product->exists)->toBeTrue();
});

it('syncs a pivot relation and marks those media attached', function (): void {
    $a = $this->service->store(UploadedFile::fake()->image('a.jpg'));
    $b = $this->service->store(UploadedFile::fake()->image('b.jpg'));
    $product = Product::create(['name' => 'Gallery']);

    $product->syncMedia('photos', [$a->id, $b->id]);

    expect($product->photos()->count())->toBe(2)
        ->and($a->fresh()->attached)->toBeTrue()
        ->and($b->fresh()->attached)->toBeTrue();
});

it('only flips currently-unattached media (idempotent)', function (): void {
    $media = $this->service->store(UploadedFile::fake()->image('a.jpg'));
    $media->update(['attached' => true]);
    $updatedAt = $media->fresh()->updated_at;

    $this->travel(1)->minute();
    Product::create(['cover_media_id' => $media->id]);

    // attached=false sharti tufayli allaqachon biriktirilgan yozuv qayta yangilanmaydi.
    expect($media->fresh()->updated_at->equalTo($updatedAt))->toBeTrue();
});
