---
name: media-development
description: Upload and attach files/images in Laravel using the nurbekjummayev/laravel-media-api package — a standalone upload API that returns a Media id, private temporary signed-URL access, per-model linking, owner/ip/user-agent auditing, and automatic orphan cleanup. Use when handling file uploads, attaching media to models, serving private files, or wiring the `attached` lifecycle.
---

# Laravel Media API

Standalone media/file upload for Laravel. Files are uploaded **once** through a single API that returns a `Media` record (with an `id` and `uuid`); every other model then links that media from **its own table/column**. Storage is private by default with temporary signed-URL access, and unlinked ("orphan") uploads are cleaned up automatically.

## When to use this skill

Use this skill when the application needs to: upload files or images via an API, attach uploaded media to a model (single FK or a per-model pivot like `product_photos`), serve/stream private files to the frontend, generate temporary view/download URLs, or reason about the `attached` flag and orphan purge.

## Core concepts

- **Standalone upload, NOT medialibrary, NO shared polymorphic pivot.** Upload returns a `Media` id; each consuming model owns its own link (FK column or its own pivot table). There is intentionally no global `mediables` table.
- **Model & namespace:** `NurbekJummayev\LaravelMediaApi\Models\Media` (table `media`).
- **Storage:** two disks registered by the package — `media` (private, `base_path('media/private')`) and `media_public` (`base_path('media/public')`). `type=private|public` per upload picks the disk. Private files are never web-served directly. Files are always stored as `Y/m/d/<uuid>.<ext>` — never the client's filename/path.
- **Signed-URL access:** private files are viewed/downloaded with Laravel **temporary signed URLs** (tamper-proof, validated against `APP_KEY`, expire after `config('media.url_ttl')`). `MediaUrlService` owns generation + validation; the `Media::$url` accessor returns a ready signed view URL and `Media::downloadUrl()` a signed download URL. Inline `view` responses send `nosniff` + a `sandbox` CSP so SVG/HTML can't run scripts.
- **Atomic upload:** the upload endpoint runs in a DB transaction. If a file can't be written or its `Media` row can't be saved, the request rolls back and every already-written file is removed — no orphan files. `MediaService::store()` also re-checks the extension allow/deny list *before* writing.
- **Audit:** each upload records `owner_id` (the authenticated user; relation resolves to `config('media.owner_model')`), plus request `ip` and `user_agent`. The stored `mime` is the server-detected (finfo) type, not the client value.
- **Orphan lifecycle:** every upload starts `attached=false`. Linking it to a model flips it to `attached=true`. The scheduled `media:purge` command deletes unattached media older than `config('media.purge_after_hours')` (default 24h) from disk + DB. **If you link media without marking it attached, it WILL be purged.**
- **Cascading delete:** deleting a model that uses `InteractsWithMedia` deletes its FK-column (`mediaColumns()`) and pivot (`mediaRelations()`) media. The physical file is removed only **after the DB transaction commits** (`DB::afterCommit`), so a rolled-back delete keeps the file. Same guarantee for `MediaService::delete()` and the `DELETE` endpoint.

## Upload & access API

Routes load under `config('media.prefix')` (default `api/v1`) with `config('media.middleware')` (default `auth:api`).

| Method | URI | Auth |
|--------|-----|------|
| `POST` | `/media` | `auth:api` + `config('media.upload_middleware')` (`can:media.upload`) |
| `GET` | `/media/{uuid}/view` | temporary signed URL (no auth) |
| `GET` | `/media/{uuid}/download` | temporary signed URL (no auth) |
| `DELETE` | `/media/{id}` | `auth:api` + `config('media.delete_middleware')` (`can:media.delete`) |

Upload field is `files[]` (one or many) plus optional `type=public|private`. The response is `Media[]`, each with `id`, `uuid`, and a temporary signed `url`. Build view/download URLs only via `$media->url` / `$media->downloadUrl()` (or `MediaUrlService`) — never hand-craft them, the signature won't match.

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

    // FK columns holding a media id → auto-marked attached on save,
    // and deleted when the model is deleted:
    protected function mediaColumns(): array
    {
        return ['cover_media_id'];
    }

    // Pivot media relations → detached + deleted when the model is deleted:
    protected function mediaRelations(): array
    {
        return ['photos'];
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

// Deleting the model deletes its FK + pivot media (file removed after commit).
$product->delete();
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
- `public_blocked_extensions` — extensions refused for `type=public` (SVG/XML/… — active content the web server serves without a CSP); still allowed on the private disk where they're sandboxed.
- `url_ttl` — minutes a signed view/download URL stays valid.
- `purge_after_hours` — orphan retention window.

## Common pitfalls

- **Forgetting to mark attached** → the file is purged within `purge_after_hours`. Use `InteractsWithMedia` or `markAttached()`.
- **Hand-building a view/download URL** → the signature won't match (403). Always use `$media->url` / `$media->downloadUrl()` (or `MediaUrlService`).
- **Expecting a public URL for private media** → private files only resolve through the signed `…/view` route, not a direct disk URL.
- **Uploading SVG/active content as `type=public`** → rejected (`public_blocked_extensions`). Use `type=private` (served sandboxed) for SVGs that need protection.
- **Trying to attach before the owning record exists** → fine here: upload first (you get an id), create/save the owner, then link. No need to pre-create the model.
- **Re-introducing a global pivot** → don't; each model owns its link (FK or its own pivot table such as `product_photos`).
