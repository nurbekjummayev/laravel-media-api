<?php

use Illuminate\Support\Facades\Route;
use Nurbekjummayev\MediaApiLibrary\Http\Controllers\MediaFolderController;
use Nurbekjummayev\MediaApiLibrary\Http\Controllers\UploadController;

Route::group([
    'prefix' => config('media-upload.routes.prefix', 'api'),
    'middleware' => config('media-upload.routes.middleware', ['api']),
], function () {
    Route::post('uploads', [UploadController::class, 'store'])->name('media.uploads.store');

    Route::apiResource('media-folders', MediaFolderController::class)->names([
        'index' => 'media.folders.index',
        'store' => 'media.folders.store',
        'show' => 'media.folders.show',
        'update' => 'media.folders.update',
        'destroy' => 'media.folders.destroy',
    ]);
});
