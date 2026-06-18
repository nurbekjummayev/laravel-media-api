---
name: media-development
description: Upload and attach files/images in Laravel using the nurbekjummayev/laravel-media-api package — a standalone upload API that returns a Media id, private token-based access, per-model linking, owner/ip/user-agent auditing, and automatic orphan cleanup. Use when handling file uploads, attaching media to models, serving private files, or wiring the `attached` lifecycle.
---

# Laravel Media API

Standalone media/file upload for Laravel. Files are uploaded **once** through a single API that returns a `Media` record (with an `id` and `uuid`); every other model then links that media from **its own table/column**. Storage is private by default with token-based access, and unlinked ("orphan") uploads are cleaned up automatically.

## When to use this skill

Use this skill when the application needs to: upload files or images via an API, attach uploaded media to a model (single FK or a per-model pivot like `product_photos`), serve/stream private files to the frontend, generate temporary view/download URLs, or reason about the `attached` flag and orphan purge.

## Core concepts

- **Standalone upload, NOT medialibrary, NO shared polymorphic pivot.** Upload returns a `Media` id; each consuming model owns its own link (FK column or its own pivot table). There is intentionally no global `mediables` table.
- **Model & namespace:** `NurbekJummayev\LaravelMediaApi\Models\Media` (table `media`).
- **Storage:** two disks registered by the package — `media` (private, `base_path('media/private')`) and `media_public` (`base_path('media/public')`). `type=private|public` per upload picks the disk. Private files are never web-served directly.
- **Token access:** private files are viewed/downloaded with an encrypted, expiring token (TTL `config('media.token_ttl')`). The `Media::$url` accessor builds a ready `…/view?token=` URL for the frontend.
- **Audit:** each upload records `owner_id` (the authenticated user; relation resolves to `config('media.owner_model')`), plus request `ip` and `user_agent`.
- **Orphan lifecycle:** every upload starts `attached=false`. Linking it to a model flips it to `attached=true`. The scheduled `media:purge` command deletes unattached media older than `config('media.purge_after_hours')` (default 24h) from disk + DB. **If you link media without marking it attached, it WILL be purged.**

## Upload & access API

Routes load under `config('media.prefix')` (default `api/v1`) with `config('media.middleware')` (default `auth:api`).

| Method | URI | Auth |
|--------|-----|------|
| `POST` | `/media` | `auth:api` + `config('media.upload_middleware')` (`can:media.upload`) |
| `GET` | `/media/{uuid}/view?token=` | token (no auth) |
| `GET` | `/media/{uuid}/download?token=` | token (no auth) |
| `DELETE` | `/media/{id}` | `auth:api` + `config('media.delete_middleware')` (`can:media.delete`) |

Upload field is `files[]` (one or many) plus optional `type=public|private`. The response is `Media[]`, each with `id`, `uuid`, and a temporary `url`.

```php
// Upload happens through the package controller; in app code you usually just
// reference the returned media id(s) coming from the frontend.
$mediaId = $request->integer('cover_media_id'); // validate: ['exists:media,id']
```

To store programmatically (e.g. in a seeder/job):

```php
use NurbekJummayev\LaravelMediaApi\Services\MediaService;

$media = app(MediaService::class)->store($uploadedFile, type: 'private', ownerId: auth()->id());
```

## Linking media to a model

Each model defines its **own** relation. Prefer the `InteractsWithMedia` trait so `attached=true` is handled automatically.

```php
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use NurbekJummayev\LaravelMediaApi\Concerns\InteractsWithMedia;
use NurbekJummayev\LaravelMediaApi\Models\Media;

class Product extends Model
{
    use InteractsWithMedia;

    // FK columns holding a media id → auto-marked attached on save:
    protected function mediaColumns(): array
    {
        return ['cover_media_id'];
    }

    // Many files via a per-model pivot table (e.g. product_photos):
    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(Media::class, 'product_photos');
    }
}
```

```php
// Single file (FK): saving the model auto-marks the media attached.
$product->cover_media_id = $mediaId; // make the column NOT NULL to require a file
$product->save();

// Many files (pivot): sync + mark attached in one call.
$product->syncMedia('photos', $mediaIds);
```

Without the trait, mark attachment explicitly after linking:

```php
app(\NurbekJummayev\LaravelMediaApi\Services\MediaService::class)->markAttached($mediaIds);
```

## Orphan cleanup

```bash
php artisan media:purge --hours=24   # also scheduled daily by the package
```

Deletes `attached=false` media older than the threshold from disk + DB. **Always link via the trait (`save` / `syncMedia`) or `markAttached(...)`, otherwise valid uploads get purged.**

## Owner

```php
$media->owner;     // belongsTo config('media.owner_model') (default = auth users model)
$media->owner_id;  // the uploading user

// config/media.php — point owner to any model:
'owner_model' => \App\Models\User::class,
```

## Configuration

`config/media.php` (publish with `php artisan vendor:publish --tag="laravel-media-api-config"`):

- `prefix`, `middleware` — route group prefix and base middleware.
- `upload_middleware`, `delete_middleware` — per-action permission middleware (rename the `can:*` permissions or add throttling here without touching the package).
- `disk`, `public_disk`, `private_root`, `public_root` — storage targets.
- `owner_model` — owner relation target (`null` → auth users model).
- `allowed_extensions`, `blocked_extensions`, `max_size`, `max_files_per_request` — upload validation.
- `token_ttl` — minutes a view/download token stays valid.
- `purge_after_hours` — orphan retention window.

## Common pitfalls

- **Forgetting to mark attached** → the file is purged within `purge_after_hours`. Use `InteractsWithMedia` or `markAttached()`.
- **Expecting a public URL for private media** → private files only resolve through the token `…/view` route, not a direct disk URL.
- **Trying to attach before the owning record exists** → fine here: upload first (you get an id), create/save the owner, then link. No need to pre-create the model.
- **Re-introducing a global pivot** → don't; each model owns its link (FK or its own pivot table such as `product_photos`).
