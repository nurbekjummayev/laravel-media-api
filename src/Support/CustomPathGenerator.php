<?php

namespace Nurbekjummayev\MediaApiLibrary\Support;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $folderPath = $media->getCustomProperty('folder_path');
        $createdAt = $media->created_at ?? now();
        $datePath = $createdAt->format('Y/m/d/H/i');

        $path = $folderPath ? "{$folderPath}/{$datePath}" : $datePath;

        return $path;
    }
}
