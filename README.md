# nurbekjummayev/laravel-media-api

[![Tests](https://github.com/nurbekjummayev/laravel-media-api/actions/workflows/tests.yml/badge.svg)](https://github.com/nurbekjummayev/laravel-media-api/actions/workflows/tests.yml)

Standalone media/file upload API for Laravel. Upload once, get an `id`, then **each model links it from its own table**. Private storage with temporary signed-URL access and automatic orphan cleanup. Built with [`spatie/laravel-package-tools`](https://github.com/spatie/laravel-package-tools).

## Requirements

- PHP `^8.3`
- Laravel 12 or 13

## Install

```bash
composer require nurbekjummayev/laravel-media-api
php artisan migrate
```

<details>
<summary>Installing as a local path package</summary>

If you keep the package in your app's `packages/` directory, add it to the root `composer.json`:

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

</details>

Files are stored under `media/private` and `media/public` (project root). Publish config if needed:

```bash
php artisan vendor:publish --tag="laravel-media-api-config"
```

## API

| Method | URI | Auth |
|--------|-----|------|
| `POST` | `/api/v1/media` | `auth:api` + `can:media.upload` |
| `GET` | `/api/v1/media/{uuid}/view` | temporary signed URL |
| `GET` | `/api/v1/media/{uuid}/download` | temporary signed URL |
| `DELETE` | `/api/v1/media/{id}` | `auth:api` + `can:media.delete` |

Upload accepts `files[]` (+ optional `type=public|private`) and returns `Media[]` with `id`, `uuid`, and a temporary signed `url`. Newly uploaded media are `attached=false`.

Uploads are **atomic** — the whole request runs in a DB transaction. If any file fails to write to disk or its `Media` record can't be saved, the transaction rolls back and every file already written for that request is removed, so no orphan files are left behind.

`view`/`download` are protected by Laravel **temporary signed URLs** (validated against `APP_KEY`, expire after `config('media.url_ttl')`). Read the signed view URL from `$media->url` and the download URL from `$media->downloadUrl()` — never hand-build these URLs. Inline `view` responses also send `X-Content-Type-Options: nosniff` and a `sandbox` CSP so SVG/HTML files can't execute scripts.

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

    // FK columns holding a media id → auto-marked attached on save,
    // and deleted when the model is deleted:
    protected function mediaColumns(): array
    {
        return ['cover_media_id'];
    }

    // Pivot media relations → cleaned up (detached + deleted) on model delete:
    protected function mediaRelations(): array
    {
        return ['photos'];
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

### Cascading delete

When a model using the trait is **deleted**, its media is cleaned up automatically: FK-column media (`mediaColumns()`) and pivot media (`mediaRelations()`) are deleted and the pivot links detached.

The physical file is only removed **after the surrounding DB transaction commits** (`DB::afterCommit`) — so if the delete is wrapped in a transaction that rolls back, the model, the media row, and the file all survive. The same guarantee applies to `MediaService::delete()` and the `DELETE` endpoint. With no active transaction the file is removed immediately.

> For soft-deletable parents the `deleting` event also fires on soft delete, so media is removed then too. Override `mediaColumns()`/`mediaRelations()` (or hook `forceDeleted` yourself) if you need to keep media until a hard delete.

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

## Security

Upload handling is hardened against the usual file-upload attacks:

- **Content-based type checks.** Validation uses Laravel's `mimes` rule, which inspects the file's *real* content via `finfo` (plus Laravel's built-in PHP-upload block) — renaming `shell.php` to `avatar.jpg` fails because the content is detected as PHP/HTML, not an image. The stored `mime` is the server-detected type, never the client-supplied `Content-Type`.
- **Extension allow/deny list, checked twice.** `StoreMediaRequest` rejects any blocked extension found in *all* parts of the filename (so `shell.php.jpg` is caught) and in the content-guessed extension. `MediaService::store()` re-checks the extension against the allow/deny list *before writing to disk*, so even direct (non-HTTP) calls can't drop a `.php`/`.phtml`/`.htaccess`/`.exe` onto a disk.
- **No client-controlled paths or names.** Files are stored under `Y/m/d/<uuid>.<ext>` — the on-disk name is always a random UUID with a sanitised (`[a-z0-9]`) extension. The original filename is kept only as a display `name`, with directory parts and control characters (incl. CR/LF, preventing `Content-Disposition` header injection) stripped.
- **Private files are sandboxed on the way out.** `view` responses send `X-Content-Type-Options: nosniff` and a `sandbox` CSP, so an SVG/HTML file opened directly can't run scripts.
- **Active content is blocked on the public disk.** Public files are served straight by the web server (no controller, so no CSP can be added). Extensions in `public_blocked_extensions` (SVG, XML, …) are therefore refused for `type=public` uploads, closing the stored-XSS hole; they remain allowed on the private disk where they're sandboxed.

> **Deployment note:** as defence-in-depth, configure your web server to *not* execute scripts (PHP, CGI) under the `media/` directories. The package never stores executable extensions, but disabling execution there removes the risk entirely even under misconfiguration.

## Config

See `config/media.php`: `owner_model`, disks, allowed/blocked extensions, `public_blocked_extensions`, max size, signed-URL TTL (`url_ttl`), purge window, route `prefix`/`middleware`, and per-action `upload_middleware`/`delete_middleware` (the `can:*` permission checks are pulled from here, so you can rename permissions or add throttling without touching the package).

## Testing

The package is tested with [Pest](https://pestphp.com) on top of `orchestra/testbench` (no full Laravel app needed). CI runs the suite on PHP 8.3/8.4 against Laravel 12.

```bash
composer install
composer test            # vendor/bin/pest
composer test-coverage   # with coverage
```
