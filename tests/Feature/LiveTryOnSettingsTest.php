<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

/**
 * Admin try-on settings (flip, scale, offsetY) stored and surfaced correctly
 * on the product page.
 */
class LiveTryOnSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    private function admin(): User
    {
        return User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    // ── product page ───────────────────────────────────────────────────

    public function test_product_page_includes_live_try_on_prop(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('liveTryOn')
                ->has('liveTryOn.frame')
                ->has('liveTryOn.flip')
                ->has('liveTryOn.scale')
                ->has('liveTryOn.offsetY')
                ->where('liveTryOn.flip', false)
                // JSON may encode PHP float 1.0 as integer 1; compare loosely.
                ->where('liveTryOn.scale', fn ($v) => $v == 1)
                ->where('liveTryOn.offsetY', fn ($v) => $v == 0)
            );
    }

    // ── admin form validation ──────────────────────────────────────────

    /** All required ProductRequest fields for the apex-01 product. */
    private function baseFields(Product $product, array $overrides = []): array
    {
        return array_merge([
            'name' => $product->name,
            'slug' => $product->slug,
            'sku' => $product->sku,
            'category' => $product->category,
            'status' => $product->status ?? 'active',
            'price' => $product->price,
            'cost' => $product->cost ?? 0,
            'stock' => $product->stock,
            'low_stock_threshold' => $product->low_stock_threshold ?? 2,
        ], $overrides);
    }

    public function test_admin_can_save_try_on_settings(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        $this->actingAs($this->admin())
            ->patchJson(route('admin.products.update', $product), $this->baseFields($product, [
                'try_on_flip' => true,
                'try_on_scale' => 1.15,
                'try_on_offset_y' => -5.0,
            ]))
            ->assertRedirect();

        $product->refresh();
        $this->assertTrue((bool) $product->try_on_flip);
        $this->assertEquals(1.15, (float) $product->try_on_scale);
        $this->assertEquals(-5.0, (float) $product->try_on_offset_y);
    }

    public function test_try_on_scale_is_clamped(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        // Values outside [0.7, 1.3] should fail validation.
        $this->actingAs($this->admin())
            ->patchJson(route('admin.products.update', $product), $this->baseFields($product, [
                'try_on_scale' => 2.5, // too large
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['try_on_scale']);
    }

    public function test_try_on_offset_y_is_clamped(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        $this->actingAs($this->admin())
            ->patchJson(route('admin.products.update', $product), $this->baseFields($product, [
                'try_on_offset_y' => -50.0, // too far
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['try_on_offset_y']);
    }

    public function test_saved_settings_appear_in_live_try_on_prop(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();
        $product->update(['try_on_flip' => true, 'try_on_scale' => 0.9, 'try_on_offset_y' => 3.5]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('liveTryOn.flip', true)
                ->where('liveTryOn.scale', 0.9)
                ->where('liveTryOn.offsetY', 3.5)
            );
    }
}
