<?php

use Illuminate\Support\Facades\Route;
use NurbekJummayev\LaravelMediaApi\Http\Controllers\MediaController;

Route::prefix((string) config('media.prefix'))
    ->middleware((array) config('media.middleware'))
    ->group(function (): void {
        Route::prefix('media')
            ->controller(MediaController::class)
            ->group(function (): void {
                // Token orqali (auth'siz) ko'rish/yuklab olish.
                Route::get('{uuid}/view', 'view')->withoutMiddleware(config('media.middleware'));
                Route::get('{uuid}/download', 'download')->withoutMiddleware(config('media.middleware'));

                // Auth + permission (configdan).
                Route::post('/', 'store')->middleware((array) config('media.upload_middleware'));
                Route::delete('{id}', 'destroy')->middleware((array) config('media.delete_middleware'));
            });
    });
