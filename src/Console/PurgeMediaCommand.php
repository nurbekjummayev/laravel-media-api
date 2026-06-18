<?php

declare(strict_types=1);

namespace NurbekJummayev\LaravelMediaApi\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use NurbekJummayev\LaravelMediaApi\Models\Media;

class PurgeMediaCommand extends Command
{
    protected $signature = 'media:purge {--hours= : Biriktirilmagan media necha soatdan eski bo\'lsa o\'chsin}';

    protected $description = 'Biriktirilmagan (attached=false) musur media fayllarni disk va bazadan o\'chiradi';

    public function handle(): int
    {
        $hours = (int) ($this->option('hours') ?? config('media.purge_after_hours', 24));
        $threshold = now()->subHours($hours);
        $count = 0;

        Media::query()
            ->unattached()
            ->where('created_at', '<', $threshold)
            ->chunkById(200, function (Collection $items) use (&$count): void {
                foreach ($items as $media) {
                    /** @var Media $media */
                    Storage::disk($media->disk)->delete($media->fullPath());
                    $media->forceDelete();
                    $count++;
                }
            });

        $this->info("Purged {$count} orphan media.");

        return self::SUCCESS;
    }
}
