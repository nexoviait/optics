<?php

namespace Database\Seeders;

use App\Enums\ImageViewType;
use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Models\ProductSpinSet;
use App\Services\ProductImageService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Geometry\Factories\EllipseFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

/**
 * Demo gallery: 5 placeholder photos (front, angle, side, on model, case) and a
 * 24-frame 360° spin set, all drawn here so no third-party imagery is used.
 * Not part of DatabaseSeeder; run it explicitly against a product:
 *
 *   php artisan db:seed --class=GallerySampleSeeder
 *   GALLERY_SAMPLE_PRODUCT=havana-keyhole php artisan db:seed --class=GallerySampleSeeder
 */
class GallerySampleSeeder extends Seeder
{
    public const SPIN_FRAMES = 24;

    public function run(ProductImageService $service): void
    {
        $slug = env('GALLERY_SAMPLE_PRODUCT');
        $product = $slug ? Product::query()->where('slug', $slug)->firstOrFail() : Product::query()->active()->ordered()->firstOrFail();
        $manager = new ImageManager(new Driver);
        $disk = Storage::disk('public');
        $views = [ImageViewType::Front, ImageViewType::Angle, ImageViewType::Side, ImageViewType::ModelFemale, ImageViewType::Case];

        foreach ($views as $n => $view) {
            $canvas = $manager->create(1080, 1080)->fill('#ffffff');
            $this->drawGlasses($canvas, match ($view) {
                ImageViewType::Angle => 0.7, ImageViewType::Side => 0.18, default => 1.0
            });
            $canvas->text(strtoupper($view->label()).' · PLACEHOLDER', 540, 1000, fn ($font) => $font->color('#9a8f88')->align('center'));

            $path = "products/{$product->id}/originals/".$service->fileName($product, $view, $n + 1).'.jpg';
            $disk->put($path, (string) $canvas->toJpeg(quality: 85));
            $image = $product->images()->create([
                'path' => $path,
                'alt' => "{$product->name} — {$view->label()}",
                'view_type' => $view,
                'is_primary' => false,
                'sort_order' => 100 + $n,
            ]);
            ProcessProductImage::dispatchSync($image);
        }

        // 360°: frame i shows the glasses rotated by i/24 of a turn (front width shrinks with cos).
        $pattern = "products/{$product->id}/spin/sample/{frame}.webp";
        for ($i = 0; $i < self::SPIN_FRAMES; $i++) {
            $frame = $manager->create(1080, 1080); // transparent
            $this->drawGlasses($frame, max(0.12, abs(cos(2 * M_PI * $i / self::SPIN_FRAMES))));
            $disk->put(ProductSpinSet::framePath($pattern, $i), (string) $frame->toWebp(quality: 80));
        }
        $product->spinSet()->first()?->delete();
        $product->spinSet()->create(['frame_count' => self::SPIN_FRAMES, 'path_pattern' => $pattern]);

        $this->command?->info('Sample gallery (5 photos) and 360° set ('.self::SPIN_FRAMES." frames) added to {$product->name}.");
    }

    /** Two lenses + bridge, squeezed horizontally by $width (1 = straight on). */
    private function drawGlasses(ImageInterface $canvas, float $width): void
    {
        $lensW = (int) round(300 * $width);
        $gap = (int) round(70 * $width);
        foreach ([-1, 1] as $side) {
            $cx = 540 + $side * (int) round(($lensW + $gap) / 2);
            $canvas->drawEllipse($cx, 520, function (EllipseFactory $ellipse) use ($lensW) {
                $ellipse->size(max(20, $lensW), 240);
                $ellipse->border('#5a3a1e', 18);
                $ellipse->background('rgba(200, 214, 224, 0.25)');
            });
        }
        $canvas->drawLine(fn ($line) => $line->from((int) (540 - $gap / 2), 470)->to((int) (540 + $gap / 2), 470)->color('#5a3a1e')->width(14));
    }
}
