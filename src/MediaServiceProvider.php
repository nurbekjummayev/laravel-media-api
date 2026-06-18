<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi;

use Illuminate\Console\Scheduling\Schedule;
use NurbekJummayev\LaravelMediaApi\Console\PurgeMediaCommand;
use NurbekJummayev\LaravelMediaApi\Services\MediaService;
use NurbekJummayev\LaravelMediaApi\Services\MediaTokenService;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-media-api')
            ->hasConfigFile('media')
            ->hasRoute('api')
            ->hasCommand(PurgeMediaCommand::class);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(MediaService::class);
        $this->app->singleton(MediaTokenService::class);
    }

    public function packageBooted(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerDisks();
        $this->schedulePurge();
    }

    /**
     * `media` (private) va `media_public` disklarini ro'yxatdan o'tkazadi.
     */
    private function registerDisks(): void
    {
        $disks = config('filesystems.disks', []);

        $disks['media'] ??= [
            'driver' => env('MEDIA_DISK_DRIVER', 'local'),
            'root' => config('media.private_root'),
            'throw' => false,
        ];

        $disks['media_public'] ??= [
            'driver' => 'local',
            'root' => config('media.public_root'),
            'url' => rtrim((string) config('app.url'), '/').'/media',
            'visibility' => 'public',
            'throw' => false,
        ];

        config(['filesystems.disks' => $disks]);
    }

    private function schedulePurge(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);
            $schedule->command('media:purge')->daily();
        });
    }
}
