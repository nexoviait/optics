<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Seeders\TestSeeder;
use Tests\TestCase;

class ProductModel3dTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    /** A minimal valid binary glTF header (magic "glTF", version 2, total length). */
    private function glb(string $name = 'frame.glb'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, 'glTF'.pack('V', 2).pack('V', 12));
    }

    private function admin(): User
    {
        return User::query()->where('role', User::ROLE_ADMIN)->firstOrFail();
    }

    public function test_admin_uploads_replaces_and_removes_a_3d_model(): void
    {
        Storage::fake('public');
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        $this->actingAs($this->admin())->postJson(route('admin.products.model.store', $product), ['model' => $this->glb()])
            ->assertOk()->assertJsonPath('model3d', fn ($url) => str_ends_with($url, '.glb'));
        $first = $product->fresh()->model_3d_path;
        Storage::disk('public')->assertExists($first);
        $this->assertStringStartsWith("products/{$product->id}/model/apex-01-", $first);

        // The product page's 360° view gets the model.
        $this->get(route('products.show', $product))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('model3d', $product->fresh()->model3dUrl()));

        // Replacing deletes the previous file.
        $this->postJson(route('admin.products.model.store', $product), ['model' => $this->glb()])->assertOk();
        Storage::disk('public')->assertMissing($first);
        $second = $product->fresh()->model_3d_path;

        $this->deleteJson(route('admin.products.model.destroy', $product))->assertOk()->assertJsonPath('model3d', null);
        Storage::disk('public')->assertMissing($second);
        $this->assertNull($product->fresh()->model_3d_path);
    }

    public function test_only_real_glb_files_are_accepted(): void
    {
        Storage::fake('public');
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();
        $this->actingAs($this->admin());

        // Wrong extension.
        $this->postJson(route('admin.products.model.store', $product), ['model' => UploadedFile::fake()->createWithContent('frame.obj', 'glTF')])
            ->assertJsonValidationErrors(['model' => 'binary glTF']);
        // Right extension, but not a glTF file.
        $this->postJson(route('admin.products.model.store', $product), ['model' => UploadedFile::fake()->createWithContent('frame.glb', 'PK zip')])
            ->assertJsonValidationErrors(['model' => 'binary glTF']);
        // Too large.
        $this->postJson(route('admin.products.model.store', $product), ['model' => UploadedFile::fake()->create('frame.glb', 40000)])
            ->assertJsonValidationErrors(['model']);

        $this->assertNull($product->fresh()->model_3d_path);
    }

    public function test_products_without_a_model_send_none(): void
    {
        $this->get(route('products.show', 'apex-01'))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('model3d', null));
    }

    public function test_models_copied_to_the_public_models_folder_are_found_by_sku(): void
    {
        Storage::fake('public');
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();
        $glb = public_path("models/{$product->sku}.glb");
        $usdz = public_path("models/{$product->sku}.usdz");
        @mkdir(dirname($glb), 0755, true);

        try {
            file_put_contents($glb, 'glTF');
            $this->assertSame(asset("models/{$product->sku}.glb"), $product->model3dUrl());
            $this->assertSame('folder', $product->model3dSource());
            $this->assertNull($product->model3dIosUrl(), 'The iPhone file is optional.');

            file_put_contents($usdz, 'usdz');
            $this->get(route('products.show', $product))->assertOk()->assertInertia(fn (Assert $page) => $page
                ->where('model3d', asset("models/{$product->sku}.glb"))
                ->where('model3dIos', asset("models/{$product->sku}.usdz")));

            // An admin upload takes priority over the folder.
            $this->actingAs($this->admin())->postJson(route('admin.products.model.store', $product), ['model' => $this->glb()])
                ->assertOk()->assertJsonPath('model3dSource', 'upload');
            $this->assertStringContainsString('/storage/products/', $product->fresh()->model3dUrl());
        } finally {
            @unlink($glb);
            @unlink($usdz);
        }
    }

    public function test_unsafe_skus_never_become_file_paths(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();
        $product->sku = '../../.env';

        $this->assertNull($product->model3dUrl());
        $this->assertNull($product->model3dSource());
    }

    public function test_guests_cannot_upload_models(): void
    {
        $product = Product::query()->where('slug', 'apex-01')->firstOrFail();

        $this->post(route('admin.products.model.store', $product), ['model' => $this->glb()])->assertRedirect();
        $this->assertNull($product->fresh()->model_3d_path);
    }
}
