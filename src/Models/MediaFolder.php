<?php

namespace Nurbekjummayev\MediaApiLibrary\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaFolder extends Model
{
    use HasUuids;

    protected $fillable = [
        'name',
        'parent_id',
        'path',
        'disk',
    ];

    protected static function booted(): void
    {
        static::creating(function (MediaFolder $folder) {
            $folder->path = $folder->computePath();
        });

        static::updating(function (MediaFolder $folder) {
            if ($folder->isDirty(['name', 'parent_id'])) {
                $folder->path = $folder->computePath();
            }
        });

        static::updated(function (MediaFolder $folder) {
            if ($folder->wasChanged('path')) {
                $folder->updateChildrenPaths();
            }
        });
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(MediaFolder::class, 'parent_id');
    }

    public function tempUploads(): HasMany
    {
        return $this->hasMany(TempUpload::class, 'folder_id');
    }

    public function computePath(): string
    {
        if ($this->parent_id && $this->parent) {
            return $this->parent->path.'/'.$this->name;
        }

        return $this->name;
    }

    protected function updateChildrenPaths(): void
    {
        $this->children->each(function (MediaFolder $child) {
            $child->path = $child->computePath();
            $child->saveQuietly();
            $child->updateChildrenPaths();
        });
    }
}
