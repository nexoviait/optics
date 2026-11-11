<?php

namespace App\Console\Commands;

use App\Jobs\ProcessProductImage;
use App\Models\ProductImage;
use Illuminate\Console\Command;

/** Generate responsive renditions for gallery photos that don't have them yet (or all, with --all). */
class ProcessProductImages extends Command
{
    protected $signature = 'products:process-images {--all : Regenerate renditions for every image} {--queue : Queue the work instead of running it now}';

    protected $description = 'Create WebP/JPEG renditions (1080/720/480 + thumbnail) for product gallery photos';

    public function handle(): int
    {
        $images = ProductImage::query()->where('is_lifestyle', false)
            ->when(! $this->option('all'), fn ($q) => $q->whereNull('renditions'))
            ->get();

        if ($images->isEmpty()) {
            $this->info('All gallery photos already have renditions.');

            return self::SUCCESS;
        }

        $this->withProgressBar($images, function (ProductImage $image) {
            $this->option('queue') ? ProcessProductImage::dispatch($image) : ProcessProductImage::dispatchSync($image);
        });
        $this->newLine();
        $this->info(($this->option('queue') ? 'Queued ' : 'Processed ').$images->count().' image(s).');

        return self::SUCCESS;
    }
}
