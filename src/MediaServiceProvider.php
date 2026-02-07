<?php

namespace Nurbekjummayev\MediaApiLibrary;

use Nurbekjummayev\MediaApiLibrary\Console\CleanupTempUploadsCommand;
use Nurbekjummayev\MediaApiLibrary\Support\CustomPathGenerator;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class MediaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('media-upload')
            ->hasConfigFile()
            ->hasMigrations([
                'create_media_folders_table',
                'create_temp_uploads_table',
            ])
            ->hasCommand(CleanupTempUploadsCommand::class);

        if (config('media-upload.routes.enabled', true)) {
            $package->hasRoute('api');
        }
    }

    public function packageBooted(): void
    {
        $this->configureMediaLibrary();
    }

    protected function configureMediaLibrary(): void
    {
        config([
            'media-library.path_generator' => CustomPathGenerator::class,
        ]);
    }
}