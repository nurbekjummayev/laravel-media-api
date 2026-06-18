<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Tests;

use Illuminate\Support\Facades\Storage;
use NurbekJummayev\ApiResponseHelper\ApiResponseHelperServiceProvider;
use NurbekJummayev\LaravelMediaApi\MediaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        // Paket disklarini soxtalashtiramiz — testlar haqiqiy faylga yozmaydi.
        Storage::fake('media');
        Storage::fake('media_public');
    }

    protected function getPackageProviders($app): array
    {
        return [
            ApiResponseHelperServiceProvider::class,
            MediaServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Route'larni auth/permission'siz ochamiz — feature testlar token oqimiga qaratilgan.
        $config->set('media.middleware', []);
        $config->set('media.upload_middleware', []);
        $config->set('media.delete_middleware', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}
