<?php

namespace Tests\Feature;

use App\Http\Resources\ProductImageResource;
use App\Jobs\ExtractSpinSet;
use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Database\Seeders\GallerySampleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;
use ZipArchive;

class ProductGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    private function admin(): User
    {
        return User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    private function apex(): Product
    {
        return Product::query()->where('slug', 'apex-01')->firstOrFail();
    }

    public function test_upload_stores_seo_named_original_and_queues_processing(): void
    {
        Storage::fake('public');
        Queue::fake();
        $product = $this->apex();

        $this->actingAs($this->admin())
            ->postJson(route('admin.products.images.store', $product), ['images' => [UploadedFile::fake()->image('IMG_0042.jpg', 1080, 1080)], 'view_type' => 'front'])
            ->assertOk()
            ->assertJsonPath('message', 'Images uploaded.');

        $image = $product->images()->reorder()->latest('id')->firstOrFail();
        $this->assertSame('front', $image->view_type->value);
        // {brand}-{model}-{color}-{view_type}-{n}-{random}.jpg
        $this->assertMatchesRegularExpression('#^products/\d+/originals/refra-atelier-rf-a01-champagne-bronze-front-\d+-[a-z0-9]{5}\.jpg$#', $image->path);
        Storage::disk('public')->assertExists($image->path);
        Queue::assertPushed(ProcessProductImage::class, fn ($job) => $job->image->is($image));
    }

    public function test_uploads_are_validated(): void
    {
        $this->actingAs($this->admin())
            ->postJson(route('admin.products.images.store', $this->apex()), ['images' => [UploadedFile::fake()->image('tiny.jpg', 120, 120)], 'view_type' => 'sideways'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['images.0', 'view_type']);
    }

    public function test_processing_generates_webp_and_jpeg_sizes_and_records_dimensions(): void
    {
        Storage::fake('public');
        $product = $this->apex();
        $path = UploadedFile::fake()->image('front.jpg', 1400, 1400)->store("products/{$product->id}/originals", 'public');
        $image = $product->images()->create(['path' => $path, 'view_type' => 'front', 'sort_order' => 50]);

        (new ProcessProductImage($image))->handle();
        $image->refresh();

        $this->assertSame([1400, 1400], [$image->width, $image->height]);
        foreach ([1080, 720, 480] as $width) {
            Storage::disk('public')->assertExists($image->renditions['webp'][$width]);
            Storage::disk('public')->assertExists($image->renditions['jpg'][$width]);
        }
        Storage::disk('public')->assertExists($image->renditions['thumb']);

        $resource = (new ProductImageResource($image))->resolve();
        $this->assertSame(['id', 'alt', 'viewType', 'width', 'height', 'isPrimary', 'sources', 'thumb'], array_keys($resource));
        $this->assertSame([480, 720, 1080], array_keys((array) $resource['sources']['webp']));
        $this->assertStringEndsWith('-1080.webp', ((array) $resource['sources']['webp'])[1080]);

        // Deleting the photo removes the original and every rendition.
        $files = [$image->path, ...$image->generatedPaths()];
        $image->delete();
        foreach ($files as $file) {
            Storage::disk('public')->assertMissing($file);
        }
    }

    public function test_small_originals_are_never_upscaled(): void
    {
        Storage::fake('public');
        $product = $this->apex();
        $path = UploadedFile::fake()->image('small.jpg', 600, 600)->store("products/{$product->id}/originals", 'public');
        $image = $product->images()->create(['path' => $path, 'sort_order' => 51]);

        (new ProcessProductImage($image))->handle();

        // 600 px original: 480 plus its own true width — never a fake "1080".
        $this->assertEqualsCanonicalizing([480, 600], array_keys($image->fresh()->renditions['webp']));
    }

    public function test_reorder_and_primary_photo(): void
    {
        $product = $this->apex();
        $a = $product->images()->create(['path' => 'images/frame-aura.jpg', 'sort_order' => 1]);
        $b = $product->images()->create(['path' => 'images/frame-solstice.jpg', 'sort_order' => 2]);
        $this->actingAs($this->admin());

        $this->patchJson(route('admin.products.images.reorder', $product), ['ids' => [$b->id, $a->id]])->assertOk();
        $this->assertSame([0, 1], [$b->fresh()->sort_order, $a->fresh()->sort_order]);

        // Another product's image can't be smuggled into this gallery.
        $foreign = Product::query()->where('slug', 'kinetics')->firstOrFail()->images()->firstOrFail();
        $this->patchJson(route('admin.products.images.reorder', $product), ['ids' => [$foreign->id]])->assertUnprocessable();

        $this->patchJson(route('admin.product-images.update', $a), ['is_primary' => true, 'view_type' => 'front', 'alt' => 'Front view'])->assertOk();
        $this->assertSame(1, $product->images()->where('is_primary', true)->count(), 'Exactly one primary photo.');
        $this->assertTrue($product->fresh()->galleryImages->first()->is($a), 'The primary photo leads the gallery.');
        $this->assertSame('Front view', $a->fresh()->alt);
    }

    public function test_product_page_only_shows_its_own_colourways_images(): void
    {
        $apex = $this->apex();
        $obsidian = Product::query()->where('slug', 'apex-01-obsidian')->firstOrFail();
        $mine = $apex->images()->create(['path' => 'images/frame-aura.jpg', 'alt' => 'Apex champagne', 'is_primary' => true]);
        $theirs = $obsidian->images()->create(['path' => 'images/frame-kinetics.jpg', 'alt' => 'Apex obsidian', 'is_primary' => true]);

        $this->get(route('products.show', $apex))->assertInertia(fn (Assert $page) => $page
            ->where('images.0.id', $mine->id)
            ->where('images', fn ($images) => ! collect($images)->contains('id', $theirs->id)));

        $this->get(route('products.show', $obsidian))->assertInertia(fn (Assert $page) => $page
            ->where('images.0.id', $theirs->id)
            ->where('images', fn ($images) => ! collect($images)->contains('id', $mine->id)));
    }

    public function test_product_page_preloads_first_image_and_embeds_json_ld(): void
    {
        $html = $this->get(route('products.show', $this->apex()))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<link rel="preload" as="image" fetchpriority="high"/', $html);
        $this->assertStringContainsString('<script type="application/ld+json" data-inertia="product-jsonld">', $html);
        $this->assertStringContainsString('"@type":"Product"', $html);
    }

    public function test_spin_archive_becomes_a_spin_set(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $product = $this->apex();

        $this->actingAs($this->admin())
            ->postJson(route('admin.products.spin-sets.store', $product), ['archive' => $this->spinZip(10)])
            ->assertOk();

        $set = $product->fresh()->spinSet;
        $this->assertNotNull($set, 'Queue runs synchronously in tests, so the set exists straight away.');
        $this->assertSame(10, $set->frame_count);
        foreach ($set->paths() as $path) {
            Storage::disk('public')->assertExists($path);
        }
        $this->assertStringEndsWith('001.webp', $set->paths()[0]);

        $this->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page->where('spinSet.frameCount', 10));
    }

    public function test_spin_archive_with_too_few_frames_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $product = $this->apex();
        $zip = $this->spinZip(3);
        $path = $zip->storeAs('spin-uploads', 'few.zip', 'local');

        $job = new ExtractSpinSet($product, $path);
        $job->withFakeQueueInteractions()->handle();

        $job->assertFailed();
        $this->assertNull($product->fresh()->spinSet);
    }

    public function test_sample_gallery_seeder_adds_five_photos_and_a_spin_set(): void
    {
        Storage::fake('public');
        $product = $this->apex();
        putenv('GALLERY_SAMPLE_PRODUCT=apex-01');

        try {
            $this->seed(GallerySampleSeeder::class);
        } finally {
            putenv('GALLERY_SAMPLE_PRODUCT');
        }

        $samples = $product->images()->where('sort_order', '>=', 100)->get();
        $this->assertSame(['front', 'angle', 'side', 'model_female', 'case'], $samples->map(fn (ProductImage $i) => $i->view_type->value)->all());
        $this->assertTrue($samples->every(fn (ProductImage $i) => $i->renditions !== null));
        $this->assertSame(GallerySampleSeeder::SPIN_FRAMES, $product->fresh()->spinSet->frame_count);
    }

    /** A ZIP of numbered PNG frames, deliberately added out of order. */
    private function spinZip(int $frames): UploadedFile
    {
        $file = tempnam(sys_get_temp_dir(), 'spin').'.zip';
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::CREATE);
        foreach (array_reverse(range(1, $frames)) as $n) {
            $img = imagecreatetruecolor(64, 64);
            ob_start();
            imagepng($img);
            $zip->addFromString("turntable/frame-{$n}.png", (string) ob_get_clean());
        }
        $zip->addFromString('__MACOSX/._frame-1.png', 'junk');
        $zip->close();

        return new UploadedFile($file, 'spin.zip', 'application/zip', null, true);
    }
}
