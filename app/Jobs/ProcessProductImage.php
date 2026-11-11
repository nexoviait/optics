<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Support\MediaUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Generates the responsive renditions for one gallery photo: WebP and JPEG at
 * each configured width (never upscaled) plus a square WebP thumbnail, and
 * records the original's pixel size so the page can reserve space for it.
 */
class ProcessProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public ProductImage $image) {}

    public function handle(): void
    {
        $source = MediaUrl::localPath($this->image->path);
        if (! is_file($source)) {
            $this->fail(new \RuntimeException("Original image missing: {$this->image->path}"));

            return;
        }

        $manager = new ImageManager(new Driver);
        $original = $manager->read($source);
        $disk = Storage::disk('public');
        $base = "products/{$this->image->product_id}/renditions/".pathinfo($this->image->path, PATHINFO_FILENAME);

        // Clear any previous renditions (re-processing after an edit).
        $disk->delete($this->image->generatedPaths());

        // Never upscale, and label every file with its true width (srcset relies on it):
        // the configured widths smaller than the original, plus one at min(original, largest).
        $configured = config('store.image_widths');
        $widths = array_filter($configured, fn (int $w) => $w < $original->width());
        $widths[] = min($original->width(), max($configured));
        $widths = array_values(array_unique($widths));
        rsort($widths);

        $renditions = ['webp' => [], 'jpg' => []];
        foreach ($widths as $width) {
            $resized = (clone $original)->scaleDown(width: $width);
            $disk->put($renditions['webp'][$width] = "{$base}-{$width}.webp", (string) $resized->toWebp(quality: 82));
            $disk->put($renditions['jpg'][$width] = "{$base}-{$width}.jpg", (string) $resized->toJpeg(quality: 85, progressive: true));
        }

        $thumb = config('store.image_thumb');
        $disk->put($renditions['thumb'] = "{$base}-thumb.webp", (string) (clone $original)->cover($thumb, $thumb)->toWebp(quality: 80));

        $this->image->forceFill([
            'width' => $original->width(),
            'height' => $original->height(),
            'renditions' => $renditions,
        ])->save();
    }
}
