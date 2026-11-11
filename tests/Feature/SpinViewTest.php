<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Support\FrameModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

class SpinViewTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    public function test_products_without_turntable_photos_have_no_360_view(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        $this->assertSame([], $product->spinUrls());
        // The React product page shows its 360° button only when a real spin set exists.
        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Product/Show')->where('spinSet', null));
    }

    public function test_frame_model_follows_shape_frame_type_and_category(): void
    {
        $apex = FrameModel::for(Product::query()->where('slug', 'apex-01')->firstOrFail());
        $kinetics = FrameModel::for(Product::query()->where('slug', 'kinetics')->firstOrFail());
        $aura = FrameModel::for(Product::query()->where('slug', 'aura-prism')->firstOrFail());

        // Two lenses + bridge + 2 end pieces + 2 temples (an all-metal frame has no acetate tips).
        $this->assertCount(7, $apex['lines']);
        $this->assertSame('metal', $apex['style']['rim'], 'Titanium fronts are thin metal eye-wires.');
        $this->assertLessThan($aura['lines'][0]['width'], $apex['lines'][0]['width'], 'Metal rims are thinner than acetate.');
        $this->assertSame(1.5, $kinetics['lines'][0]['width'], 'Rimless frames get hairline rims.');
        $this->assertTrue($aura['tinted'], 'Sunglasses have tinted lenses.');
        $this->assertFalse($apex['tinted']);
        $this->assertCount(40, $aura['lines'][0]['points'], 'Round panto lenses are drawn as smooth curves.');
        $this->assertSame('#C9A887', $apex['color']);
    }

    public function test_admin_uploads_photos_in_natural_file_name_order(): void
    {
        Storage::fake('public');
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        // Uploaded out of order: 10 must sort after 2.
        $names = ['frame-10.jpg', 'frame-2.jpg', 'frame-1.jpg', 'frame-3.jpg', 'frame-4.jpg', 'frame-5.jpg', 'frame-6.jpg', 'frame-7.jpg', 'frame-8.jpg', 'frame-9.jpg'];
        $files = array_map(fn ($n) => UploadedFile::fake()->image($n, 40, 30), $names);

        $this->actingAs($admin)->post(route('admin.products.spin.store', $product), ['frames' => $files])->assertSessionHasNoErrors();

        $frames = $product->fresh()->spinFrames;
        $this->assertCount(10, $frames);
        $this->assertSame(range(0, 9), $frames->pluck('position')->all());
        foreach ($frames as $frame) {
            Storage::disk('public')->assertExists($frame->path);
        }

        // With photos the product page gets the sequence for its 360° view.
        $this->get(route('products.show', $product))
            ->assertInertia(fn (Assert $page) => $page->where('spinSet.frameCount', 10)->where('spinSet.frames.0', $frames->first()->url()));

        // Replacing removes the old files.
        $old = $frames->first()->path;
        $this->post(route('admin.products.spin.store', $product), ['frames' => array_map(fn ($n) => UploadedFile::fake()->image($n), $names)]);
        Storage::disk('public')->assertMissing($old);

        $this->delete(route('admin.products.spin.destroy', $product));
        $this->assertCount(0, $product->fresh()->spinFrames);
    }

    public function test_too_few_photos_are_rejected(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
        $product = Product::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('admin.products.spin.store', $product), ['frames' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')]])
            ->assertSessionHasErrors('frames');
    }
}
