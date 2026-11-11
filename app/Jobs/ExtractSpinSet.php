<?php

namespace App\Jobs;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductSpinSet;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use ZipArchive;

/**
 * Turns an uploaded ZIP of turntable photos into a spin set: frames are ordered
 * by file name (natural sort, as turntable cameras number shots), converted to
 * WebP (transparency kept) at most 1080 px wide and saved as 001.webp, 002.webp…
 */
class ExtractSpinSet implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const MIN_FRAMES = 8;

    public const MAX_FRAMES = 72;

    public int $timeout = 300;

    public function __construct(public Product $product, public string $zipPath) {}

    public function handle(): void
    {
        $zip = new ZipArchive;
        if ($zip->open(Storage::disk('local')->path($this->zipPath)) !== true) {
            $this->fail(new \RuntimeException('The 360° archive could not be opened.'));

            return;
        }

        try {
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                // Skip folders, macOS metadata and anything that isn't a photo.
                if (str_ends_with($name, '/') || str_contains($name, '__MACOSX') || ! preg_match('/\.(jpe?g|png|webp)$/i', $name)) {
                    continue;
                }
                $entries[] = $name;
            }
            usort($entries, fn ($a, $b) => strnatcasecmp(basename($a), basename($b)));

            if (count($entries) < self::MIN_FRAMES || count($entries) > self::MAX_FRAMES) {
                $this->fail(new \RuntimeException('A 360° archive needs '.self::MIN_FRAMES.'–'.self::MAX_FRAMES.' photos; found '.count($entries).'.'));

                return;
            }

            $manager = new ImageManager(new Driver);
            $disk = Storage::disk('public');
            $pattern = "products/{$this->product->id}/spin/".now()->format('YmdHis').'/{frame}.webp';

            foreach ($entries as $index => $name) {
                $frame = $manager->read((string) $zip->getFromName($name))->scaleDown(width: 1080);
                $disk->put(ProductSpinSet::framePath($pattern, $index), (string) $frame->toWebp(quality: 80));
            }

            DB::transaction(function () use ($entries, $pattern) {
                $this->product->spinSet()->first()?->delete(); // removes the previous set's files
                $this->product->spinSet()->create(['frame_count' => count($entries), 'path_pattern' => $pattern]);
            });

            ActivityLog::record('360° view updated ('.count($entries).' frames, ZIP)', $this->product);
        } finally {
            $zip->close();
            Storage::disk('local')->delete($this->zipPath);
        }
    }
}
