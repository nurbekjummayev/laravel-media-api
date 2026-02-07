<?php

namespace Nurbekjummayev\MediaApiLibrary\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Nurbekjummayev\MediaApiLibrary\Http\Requests\StoreUploadRequest;
use Nurbekjummayev\MediaApiLibrary\Http\Resources\TempUploadResource;
use Nurbekjummayev\MediaApiLibrary\Models\TempUpload;

class UploadController extends Controller
{
    public function store(StoreUploadRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $disk = config('media-upload.disks.temp', 'local');

        $now = now();
        $datePath = $now->format('Y/m/d/H/i');
        $filename = Str::uuid().'.'.$file->getClientOriginalExtension();
        $fullPath = "temp/{$datePath}/{$filename}";

        $path = $file->storeAs("temp/{$datePath}", $filename, $disk);

        $tempUpload = TempUpload::create([
            'folder_id' => $request->input('folder_id'),
            'temp_path' => $path,
            'disk' => $disk,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return response()->json([
            'data' => new TempUploadResource($tempUpload),
        ], 201);
    }
}
