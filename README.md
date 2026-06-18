# nurbekjummayev/laravel-media-api

Standalone media/file upload API for Laravel. Upload once, get an `id`, then **each model links it from its own table**. Private storage with token-based access and automatic orphan cleanup. Built with [`spatie/laravel-package-tools`](https://github.com/spatie/laravel-package-tools).

## Install (local path package)

Root `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "packages/nurbekjummayev/laravel-media-api" }
],
"require": {
    "nurbekjummayev/laravel-media-api": "*"
}
```

```bash
composer update nurbekjummayev/laravel-media-api
php artisan migrate
```

Files are stored under `media/private` and `media/public` (project root). Publish config if needed:

```bash
php artisan vendor:publish --tag="laravel-media-api-config"
```

## API

| Method | URI | Auth |
|--------|-----|------|
| `POST` | `/api/v1/media` | `auth:api` + `can:media.upload` |
| `GET` | `/api/v1/media/{uuid}/view?token=` | token |
| `GET` | `/api/v1/media/{uuid}/download?token=` | token |
| `DELETE` | `/api/v1/media/{id}` | `auth:api` + `can:media.delete` |

Upload accepts `files[]` (+ optional `type=public|private`) and returns `Media[]` with `id`, `uuid`, and a temporary `url`. Newly uploaded media are `attached=false`.

## Linking media — each model owns its link

There is **no shared polymorphic pivot**. A model that needs media defines its own table/column.

**Many files — a dedicated per-model table** (e.g. `product_photos`):

```php
// migration
Schema::create('product_photos', function (Blueprint $table) {
    $table->id();
    $table->foreignId('product_id')->constrained()->cascadeOnDelete();
    $table->foreignId('media_id')->constrained('media');
    $table->integer('sort')->default(0);
});

// after saving the links:
app(\NurbekJummayev\LaravelMediaApi\Services\MediaService::class)->markAttached($mediaIds);
```

**Single file — an FK column** on the model:

```php
// migration: $table->foreignId('cover_media_id')->nullable()->constrained('media');
$product->cover_media_id = $request->integer('cover_media_id'); // validate exists:media,id
$product->save();
app(\NurbekJummayev\LaravelMediaApi\Services\MediaService::class)->markAttached([$product->cover_media_id]);
```

### Auto-marking `attached` (recommended)

Use the `InteractsWithMedia` trait so linking flips `attached=true` automatically:

```php
use NurbekJummayev\LaravelMediaApi\Concerns\InteractsWithMedia;

class Product extends Model
{
    use InteractsWithMedia;

    // FK columns holding a media id → auto-marked attached on save:
    protected function mediaColumns(): array
    {
        return ['cover_media_id'];
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_photos');
    }
}

$product->cover_media_id = $id;
$product->save();                  // cover media → attached=true automatically

$product->syncMedia('photos', $ids); // pivot sync + attached=true
```

Without the trait, call `app(MediaService::class)->markAttached($ids)` yourself after linking.

## Orphan cleanup

Newly uploaded media are `attached=false`. Linking flips them to `attached=true` (via the trait above or `MediaService::markAttached($ids)`). The scheduled `media:purge` command (daily) deletes unattached media older than `config('media.purge_after_hours')` (24h) from disk + DB:

```bash
php artisan media:purge --hours=24
```

> Always call `markAttached` after linking, otherwise the file is purged.

## Owner

Each `Media` row stores an `owner_id` (set to the authenticated user on upload). The owner relation resolves to `config('media.owner_model')` — set it to `User::class` or any other model; `null` falls back to `config('auth.providers.users.model')`.

```php
$media->owner; // belongsTo config('media.owner_model')
```

Each upload also records the request `ip` and `user_agent`.

## Config

See `config/media.php`: `owner_model`, disks, allowed/blocked extensions, max size, token TTL, purge window, route `prefix`/`middleware`, and per-action `upload_middleware`/`delete_middleware` (the `can:*` permission checks are pulled from here, so you can rename permissions or add throttling without touching the package).
