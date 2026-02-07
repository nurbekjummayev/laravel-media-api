<?php

namespace Nurbekjummayev\MediaApiLibrary\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nurbekjummayev\MediaApiLibrary\Models\TempUpload;

/**
 * @mixin TempUpload
 */
class TempUploadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'file_id' => $this->id,
            'folder_id' => $this->folder_id,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
        ];
    }
}
