<?php

namespace Local\MediaLibrary;

use Spatie\MediaLibrary\HasMedia as SpatieHasMedia;

/**
 * @property array<string, array{field: string, multiple: bool}|string> $mediaIds
 */
interface HasMedia extends SpatieHasMedia
{
    /**
     * Get the media collection mappings.
     *
     * @return array<string, array{field: string, multiple: bool}|string>
     */
    public function getMediaIds(): array;
}
