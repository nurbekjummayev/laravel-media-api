<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property string $file
 * @property string $name
 * @property string $ext
 * @property string|null $mime
 * @property int $size
 * @property string $type
 * @property int|null $owner_id
 * @property string|null $ip
 * @property string|null $user_agent
 * @property bool $attached
 */
class Media extends Model
{
    use SoftDeletes;

    protected $table = 'media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uuid', 'disk', 'path', 'file', 'name', 'ext',
        'mime', 'size', 'hash', 'type', 'owner_id', 'ip', 'user_agent', 'attached',
    ];

    /**
     * @var list<string>
     */
    protected $appends = ['url'];

    protected static function booted(): void
    {
        static::creating(function (Media $media): void {
            $media->uuid ??= (string) Str::uuid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attached' => 'boolean',
            'size' => 'integer',
        ];
    }

    /**
     * Diskdagi to'liq yo'l.
     */
    public function fullPath(): string
    {
        return trim($this->path, '/').'/'.$this->file;
    }

    /**
     * Vaqtinchalik imzolangan (signed) ko'rish URL'i — frontda <img>/<a> uchun.
     */
    public function getUrlAttribute(): string
    {
        return URL::temporarySignedRoute(
            'media.view',
            now()->addMinutes((int) config('media.url_ttl', 60)),
            ['uuid' => $this->uuid],
        );
    }

    /**
     * Vaqtinchalik imzolangan yuklab olish (download) URL'i.
     */
    public function downloadUrl(): string
    {
        return URL::temporarySignedRoute(
            'media.download',
            now()->addMinutes((int) config('media.url_ttl', 60)),
            ['uuid' => $this->uuid],
        );
    }

    /**
     * Media egasi — config('media.owner_model') (default: auth users modeli).
     */
    public function owner(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('media.owner_model') ?: config('auth.providers.users.model');

        return $this->belongsTo($model, 'owner_id');
    }

    public function scopeAttached(Builder $query): void
    {
        $query->where('attached', true);
    }

    public function scopeUnattached(Builder $query): void
    {
        $query->where('attached', false);
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk($this->disk)->exists($this->fullPath());
    }
}
