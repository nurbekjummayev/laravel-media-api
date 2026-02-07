<?php

namespace Local\MediaLibrary\Console;

use Illuminate\Console\Command;
use Local\MediaLibrary\Models\TempUpload;

class CleanupTempUploadsCommand extends Command
{
    protected $signature = 'media:cleanup-temp {--hours= : Number of hours after which to delete unattached uploads}';

    protected $description = 'Clean up old unattached temporary uploads';

    public function handle(): int
    {
        $hours = $this->option('hours') ?? config('media-upload.cleanup_hours', 24);

        $query = TempUpload::query()
            ->unattached()
            ->olderThan((int) $hours);

        $count = $query->count();

        if ($count === 0) {
            $this->info('No temporary uploads to clean up.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} temporary upload(s) older than {$hours} hour(s).");

        $deleted = 0;

        $query->each(function (TempUpload $upload) use (&$deleted) {
            $upload->deleteFile();
            $upload->delete();
            $deleted++;
        });

        $this->info("Deleted {$deleted} temporary upload(s).");

        return self::SUCCESS;
    }
}
