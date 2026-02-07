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
        $modelType = class_basename($media->model_type);
        $modelId = $media->model_id;
        $folderPath = $media->getCustomProperty('folder_path');

        $path = "media/{$modelType}/{$modelId}";

        if ($folderPath) {
            $path .= "/{$folderPath}";
        }

        return $path;
    }
}
