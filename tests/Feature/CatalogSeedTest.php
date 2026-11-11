<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_catalogue_seeds_photographed_frames(): void
    {
        // The in-memory database is shared across tests and may already hold the demo frames.
        Product::query()->delete();
        $this->seed(DatabaseSeeder::class);

        $products = Product::query()->get();
        $this->assertCount(8, $products->where('category', 'eyeglasses'));
        $this->assertCount(10, $products->where('category', 'sunglasses'));
        foreach ($products as $product) {
            $this->assertFileExists(public_path($product->image), "{$product->name} is missing its photo.");
            $this->assertSame($product->image, $product->galleryImages()->first()?->path, "{$product->name}'s photo should lead its gallery.");
        }

        $this->get('/eyeglasses')->assertOk()->assertSee('Havana Keyhole')->assertSee(asset('images/products/havana-keyhole.jpg'), false);
        $this->get(route('products.show', 'noir-sovereign'))->assertOk()->assertSee('Noir Sovereign');
        $this->get('/sunglasses')->assertOk()->assertSee('Azure Mirror Aviator');
    }
}
