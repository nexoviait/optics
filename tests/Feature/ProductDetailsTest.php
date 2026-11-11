<?php

namespace Tests\Feature;

use App\Models\LensOption;
use App\Models\Page;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

class ProductDetailsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    private function admin(): User
    {
        return User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    public function test_product_page_shows_brand_pricing_specs_variants_and_gallery(): void
    {
        $apex = Product::query()->where('slug', 'apex-01')->firstOrFail();

        $this->get(route('products.show', $apex))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Product/Show')
                ->where('product.brand', 'REFRA Atelier')
                ->where('product.styleLine', 'Champagne Bronze Full Rim Architectural Square')
                ->where('product.compareAtPrice', 720)          // original price
                ->where('product.discountPercent', 25)          // (720 - 540) / 720
                ->where('product.offerText', 'with Free Anti-Glare lenses')
                ->where('product.specs.0.rows', fn ($rows) => collect($rows)->contains(fn ($r) => $r['label'] === 'Model No.' && $r['value'] === 'RF-A01'))
                ->where('product.specs.2.rows', fn ($rows) => collect($rows)->contains('label', 'Temple Material'))
                ->has('images', fn (Assert $images) => $images->each(fn (Assert $image) => $image->hasAll(['id', 'alt', 'viewType', 'width', 'height', 'isPrimary', 'sources.webp', 'sources.jpg', 'thumb'])))
                ->has('lifestyle', 3)
                ->where('variants', fn ($variants) => collect($variants)->contains('url', route('products.show', 'apex-01-obsidian'))) // colour variant link
                ->where('meta.jsonLd.@type', 'Product')
                ->where('meta.jsonLd.offers.price', '540.00'));
    }

    public function test_discount_requires_higher_compare_at_price(): void
    {
        $product = Product::query()->where('slug', 'kinetics')->firstOrFail();
        $this->assertFalse($product->isDiscounted());

        $product->compare_at_price = 500; // lower than price → ignored
        $this->assertFalse($product->isDiscounted());
        $this->assertSame(0, $product->discountPercent());
    }

    public function test_catalog_filters_facets_and_sorting(): void
    {
        $this->get('/eyeglasses?frame_type[]=rimless')
            ->assertOk()
            ->assertSee('Kinetics Rimless')
            ->assertDontSee('Solstice Geometric');

        $this->get('/eyeglasses?gender[]=women&category=sunglasses')
            ->assertSee('Aura Prism Sun')
            ->assertSee('Eclipse Bold Acetate')
            ->assertDontSee('Helios Aviator 02');

        $this->get('/eyeglasses?price_max=500')->assertSee('Eclipse Bold Acetate')->assertDontSee('Kinetics Rimless');

        $response = $this->get('/eyeglasses?sort=price_desc')->assertOk();
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'Eclipse Bold Acetate'), strpos($html, 'Kinetics Rimless'));
    }

    public function test_search_page_and_suggestions(): void
    {
        $this->get('/search?q=aviator')->assertOk()->assertSee('Helios Aviator 02')->assertDontSee('Kinetics Rimless');

        $this->getJson('/search/suggest?q=tit')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Kinetics Rimless']);

        $this->getJson('/search/suggest?q=a')->assertOk()->assertJson(['products' => []]);
    }

    public function test_guest_wishlist_merges_into_account_on_login(): void
    {
        $product = Product::query()->firstOrFail();

        $this->postJson(route('wishlist.toggle', $product))->assertOk()->assertJson(['saved' => true, 'count' => 1]);
        $this->get('/wishlist')->assertOk()->assertSee($product->name);

        $customer = User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
        $this->post('/login', ['email' => $customer->email, 'password' => 'password'])->assertRedirect();

        $this->assertDatabaseHas('wishlist_items', ['user_id' => $customer->id, 'product_id' => $product->id]);

        $this->postJson(route('wishlist.toggle', $product))->assertJson(['saved' => false, 'count' => 0]);
        $this->assertDatabaseMissing('wishlist_items', ['user_id' => $customer->id, 'product_id' => $product->id]);
    }

    public function test_wishlist_and_cart_buttons_use_server_generated_urls(): void
    {
        $product = Product::query()->where('slug', 'aura-prism')->firstOrFail();
        $url = route('wishlist.toggle', $product);

        $button = 'wishlistButton('.Js::from($url);

        // Regression: the heart button used to build /wishlist/{id}, but products bind by slug (404).
        $this->get('/sunglasses')->assertOk()->assertSee($button, false);
        $this->get(route('products.show', $product))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('product.urls.wishlist', $url)->where('product.urls.cart', route('cart.store')))
            ->assertSee('<meta name="cart-url" content="'.route('cart.index').'">', false);

        $this->postJson($url)->assertOk()->assertJson(['saved' => true]);
    }

    public function test_draft_products_cannot_be_wishlisted(): void
    {
        $product = Product::query()->firstOrFail();
        $product->update(['status' => 'draft']);

        $this->postJson(route('wishlist.toggle', $product))->assertNotFound();
    }

    public function test_lens_packages_expose_features(): void
    {
        $this->get(route('products.show', 'apex-01'))->assertInertia(fn (Assert $page) => $page
            ->where('lensOptions', fn ($options) => collect($options)->contains(fn ($o) => in_array('Up to 35% thinner & lighter', $o['features'], true))
                && collect($options)->contains('badge', 'Most popular')));
    }

    public function test_review_titles_are_saved_and_shown(): void
    {
        $customer = User::query()->where('role', User::ROLE_CUSTOMER)->firstOrFail();
        $product = Product::query()->where('slug', 'helios-02')->firstOrFail();

        $this->actingAs($customer)->post(route('products.reviews.store', $product), [
            'reviewer_name' => 'Christian', 'rating' => 4, 'title' => 'Great for driving', 'body' => 'Sharp contrast on long motorway drives.',
        ])->assertRedirect();

        $this->get(route('products.show', $product))->assertInertia(fn (Assert $page) => $page->where('reviews.items.0.title', 'Great for driving')->where('reviews.hasReviewed', true));
    }

    public function test_content_pages_render_markdown_safely_and_respect_publishing(): void
    {
        $this->get('/pages/faq')->assertOk()->assertSee('<h2>Orders &amp; delivery</h2>', false);

        $page = Page::query()->create(['title' => 'Test', 'body' => "<script>alert(1)</script>\n\n**bold**", 'is_published' => true]);
        $this->get(route('pages.show', $page))->assertDontSee('<script>alert(1)</script>', false)->assertSee('<strong>bold</strong>', false);

        $page->update(['is_published' => false]);
        $this->get(route('pages.show', $page))->assertNotFound();

        $this->get('/')->assertSee(route('pages.show', 'privacy')); // footer link
    }

    public function test_admin_manages_product_details_images_lens_packages_and_pages(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin());
        $product = Product::query()->where('slug', 'kinetics')->firstOrFail();

        $this->get(route('admin.products.edit', $product))->assertOk()->assertSee('Gallery & Style Stories');

        $payload = $product->only(['name', 'sku', 'category', 'status', 'price', 'cost', 'stock', 'low_stock_threshold', 'frame_type', 'gender'])
            + ['finishes' => ['#0F1113'], 'brand' => 'REFRA Lab', 'compare_at_price' => 600];
        $this->put(route('admin.products.update', $product), $payload)->assertSessionHasErrors('compare_at_price');

        $this->put(route('admin.products.update', $product), ['compare_at_price' => 800] + $payload)->assertSessionHasNoErrors();
        $this->assertSame('REFRA Lab', $product->fresh()->brand);
        $this->assertSame('kinetics', $product->fresh()->slug, 'Saving without a slug must keep the existing URL.');

        $this->post(route('admin.products.images.store', $product), ['images' => [UploadedFile::fake()->image('side.jpg', 1080, 1080)], 'is_lifestyle' => 0])->assertSessionHasNoErrors();
        $image = $product->images()->reorder()->latest('id')->firstOrFail();
        Storage::disk('public')->assertExists($image->path);

        $this->delete(route('admin.product-images.destroy', $image));
        Storage::disk('public')->assertMissing($image->path);

        $option = LensOption::query()->where('code', 'antiglare')->firstOrFail();
        $this->put('/admin/lenses/pricing', ['lens_options' => [$option->id => ['label' => $option->label, 'price' => 0, 'is_active' => 1, 'badge' => 'Free', 'features' => "Anti-reflective\n\nScratch resistant"]]]);
        $this->assertSame(['Anti-reflective', 'Scratch resistant'], $option->fresh()->features);

        $this->get('/admin/pages')->assertOk()->assertSee('Frequently Asked Questions');
        $this->post('/admin/pages', ['title' => 'Store Locator', 'body' => '## Geneva', 'footer_group' => 'company', 'is_published' => 1])->assertRedirect();
        $this->get('/pages/store-locator')->assertOk()->assertSee('Geneva');
    }
}
