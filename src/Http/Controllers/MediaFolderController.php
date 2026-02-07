<?php

namespace Nurbekjummayev\MediaApiLibrary\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Nurbekjummayev\MediaApiLibrary\Http\Requests\StoreMediaFolderRequest;
use Nurbekjummayev\MediaApiLibrary\Http\Requests\UpdateMediaFolderRequest;
use Nurbekjummayev\MediaApiLibrary\Http\Resources\MediaFolderResource;
use Nurbekjummayev\MediaApiLibrary\Models\MediaFolder;

class MediaFolderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $folders = MediaFolder::query()
            ->whereNull('parent_id')
            ->with('children')
            ->orderBy('name')
            ->get();

        return MediaFolderResource::collection($folders);
    }

    public function store(StoreMediaFolderRequest $request): JsonResponse
    {
        $folder = MediaFolder::create([
            'name' => $request->input('name'),
            'parent_id' => $request->input('parent_id'),
            'disk' => $request->input('disk', 'public'),
        ]);

        return response()->json([
            'data' => new MediaFolderResource($folder),
        ], 201);
    }

    public function show(MediaFolder $folder): MediaFolderResource
    {
        $folder->load('children');

        return new MediaFolderResource($folder);
    }

    public function update(UpdateMediaFolderRequest $request, MediaFolder $folder): MediaFolderResource
    {
        $folder->update($request->only(['name', 'parent_id', 'disk']));

        return new MediaFolderResource($folder->fresh());
    }

    public function destroy(MediaFolder $folder): JsonResponse
    {
        $folder->delete();

        return response()->json(null, 204);
    }
}
