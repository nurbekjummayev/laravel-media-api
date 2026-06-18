<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use NurbekJummayev\LaravelMediaApi\Models\Media;
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

it('purges fk-column media and its file when the model is deleted', function (): void {
    $cover = $this->service->store(UploadedFile::fake()->image('cover.jpg'));
    $path = $cover->fullPath();
    $product = Product::create(['cover_media_id' => $cover->id]);

    $product->delete();

    expect(Media::find($cover->id))->toBeNull();
    Storage::disk('media')->assertMissing($path);
});

it('purges pivot media and detaches the relation when the model is deleted', function (): void {
    $a = $this->service->store(UploadedFile::fake()->image('a.jpg'));
    $b = $this->service->store(UploadedFile::fake()->image('b.jpg'));
    $product = Product::create(['name' => 'Gallery']);
    $product->syncMedia('photos', [$a->id, $b->id]);

    $product->delete();

    expect(Media::find($a->id))->toBeNull()
        ->and(Media::find($b->id))->toBeNull()
        ->and(DB::table('product_media')->count())->toBe(0);
    Storage::disk('media')->assertMissing($a->fullPath());
});

it('keeps the file and media when the deleting transaction rolls back', function (): void {
    $cover = $this->service->store(UploadedFile::fake()->image('cover.jpg'));
    $path = $cover->fullPath();
    $product = Product::create(['cover_media_id' => $cover->id]);

    try {
        DB::transaction(function () use ($product): void {
            $product->delete();
            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
        // kutilgan
    }

    // Rollback: model ham, media ham, fayl ham saqlanib qoladi.
    expect(Product::find($product->id))->not->toBeNull()
        ->and(Media::find($cover->id))->not->toBeNull();
    Storage::disk('media')->assertExists($path);
});
