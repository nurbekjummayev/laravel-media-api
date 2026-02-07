# Laravel Media API

Two-step upload media system with folder organization for Laravel API applications.

## Installation

```bash
composer require nurbekjummayev/laravel-media-api
```

Publish config and migrations:

```bash
php artisan vendor:publish --provider="Nurbekjummayev\MediaApiLibrary\MediaServiceProvider"
php artisan migrate
```

## Configuration

```php
// config/media-upload.php
return [
    'disks' => [
        'temp' => 'local',      // Temporary uploads
        'public' => 'public',   // Public files
        'private' => 'local',   // Private files
    ],
    'cleanup_hours' => 24,
    'routes' => [
        'enabled' => true,
        'prefix' => 'api',
        'middleware' => ['api'],
    ],
];
```

## File Structure

```
storage/
├── app/temp/2024/01/15/14/30/uuid.jpg      # Temporary
├── app/public/photos/2024/01/15/14/30/     # Public
└── app/private/docs/2024/01/15/14/30/      # Private
```

## Usage

### 1. Setup Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Nurbekjummayev\MediaApiLibrary\HasMedia;
use Nurbekjummayev\MediaApiLibrary\InteractsWithMedia;

class Post extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['title', 'content'];

    public function getMediaIds(): array
    {
        return [
            // Simple - single file
            'thumbnail' => 'thumbnail_id',

            // Multiple files
            'gallery' => 'gallery_ids',

            // Full configuration
            'avatar' => [
                'field' => 'avatar_id',
                'multiple' => false,
                'disk' => 'public',
                'accept' => ['image/jpeg', 'image/png', 'image/webp'],
                'max_size' => 5, // MB
                'conversions' => [
                    'thumb' => ['width' => 150, 'height' => 150],
                    'medium' => ['width' => 600, 'height' => 400],
                ],
            ],

            // Private documents
            'documents' => [
                'field' => 'document_ids',
                'multiple' => true,
                'disk' => 'private',
                'accept' => ['application/pdf'],
                'max_size' => 20,
                'max_files' => 10,
            ],
        ];
    }
}
```

### 2. Upload File (Step 1)

```bash
# POST /api/uploads
curl -X POST http://localhost/api/uploads \
  -F "file=@photo.jpg" \
  -F "folder_id=optional-folder-uuid"
```

Response:
```json
{
    "data": {
        "file_id": "550e8400-e29b-41d4-a716-446655440000",
        "folder_id": null,
        "original_name": "photo.jpg",
        "mime_type": "image/jpeg",
        "size": 102400
    }
}
```

### 3. Attach to Model (Step 2)

```php
// Controller
public function store(Request $request)
{
    $validated = $request->validate([
        'title' => 'required|string',
        'thumbnail_id' => ['nullable', new ValidateMedia(Post::class, 'thumbnail')],
        'gallery_ids' => ['nullable', 'array'],
        'gallery_ids.*' => ['uuid'],
    ]);

    // Files automatically attach on create
    $post = Post::create($validated);

    return response()->json($post->load('thumbnail', 'gallery'));
}
```

### 4. Access Media

```php
// Single file
$post->thumbnail;              // Media model
$post->thumbnail->getUrl();    // URL
$post->thumbnail->getUrl('thumb'); // Conversion URL

// Multiple files
$post->gallery;                // Collection
$post->gallery->first()->getUrl();

// Get all media
$post->getMedia('gallery');
```

## API Endpoints

### Uploads

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/uploads` | Upload temporary file |

### Media Folders

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/media-folders` | List root folders |
| POST | `/api/media-folders` | Create folder |
| GET | `/api/media-folders/{id}` | Show folder |
| PUT | `/api/media-folders/{id}` | Update folder |
| DELETE | `/api/media-folders/{id}` | Delete folder |

## Validation

```php
use Nurbekjummayev\MediaApiLibrary\Rules\ValidateMedia;

$request->validate([
    'avatar_id' => ['nullable', new ValidateMedia(User::class, 'avatar')],
    'document_ids' => ['nullable', 'array'],
    'document_ids.*' => ['uuid'],
]);
```

ValidateMedia checks:
- File exists
- File not already used
- MIME type allowed
- File size within limit
- Max files count

## Image Conversions

```php
public function getMediaIds(): array
{
    return [
        'photos' => [
            'field' => 'photo_ids',
            'multiple' => true,
            'conversions' => [
                'thumb' => [
                    'width' => 150,
                    'height' => 150,
                    'fit' => \Spatie\Image\Enums\Fit::Crop,
                ],
                'large' => [
                    'width' => 1200,
                    'height' => 800,
                    'format' => 'webp',
                    'quality' => 80,
                    'queued' => true,
                ],
            ],
        ],
    ];
}
```

## Cleanup Temporary Files

```bash
# Clean files older than 24 hours (default)
php artisan media:cleanup-temp

# Custom hours
php artisan media:cleanup-temp --hours=48
```

Add to scheduler:

```php
// app/Console/Kernel.php
$schedule->command('media:cleanup-temp')->daily();
```

## Frontend Example (JavaScript)

```javascript
// Upload file
async function uploadFile(file) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await fetch('/api/uploads', {
        method: 'POST',
        body: formData,
    });

    const { data } = await response.json();
    return data.file_id;
}

// Create post with media
async function createPost(title, thumbnailFile, galleryFiles) {
    const thumbnailId = await uploadFile(thumbnailFile);
    const galleryIds = await Promise.all(galleryFiles.map(uploadFile));

    const response = await fetch('/api/posts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            title,
            thumbnail_id: thumbnailId,
            gallery_ids: galleryIds,
        }),
    });

    return response.json();
}
```

## License

MIT