<?php

namespace Local\MediaLibrary\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TempUpload extends Model
{
    use HasUuids;

    protected $fillable = [
        'folder_id',
        'temp_path',
        'disk',
        'original_name',
        'mime_type',
        'size',
        'is_attached',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'is_attached' => 'boolean',
        ];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id');
    }

    public function scopeUnattached(Builder $query): Builder
    {
        return $query->where('is_attached', false);
    }

    public function scopeOlderThan(Builder $query, int $hours): Builder
    {
        return $query->where('created_at', '<', now()->subHours($hours));
    }

    protected function fullPath(): Attribute
    {
        return Attribute::get(fn () => Storage::disk($this->disk)->path($this->temp_path));
    }

    public function fileExists(): bool
    {
        return Storage::disk($this->disk)->exists($this->temp_path);
    }

    public function deleteFile(): bool
    {
        if ($this->fileExists()) {
            return Storage::disk($this->disk)->delete($this->temp_path);
        }

        return true;
    }

    public function markAsAttached(): bool
    {
        return $this->update(['is_attached' => true]);
    }

    public function getFolderPath(): ?string
    {
        return $this->folder?->path;
    }
}
