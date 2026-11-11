<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderProductImagesRequest;
use App\Http\Requests\Admin\StoreProductImagesRequest;
use App\Http\Requests\Admin\StoreProductModelRequest;
use App\Http\Requests\Admin\StoreSpinSetRequest;
use App\Http\Requests\Admin\UpdateProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Gallery management. Every action answers JSON for the React images manager
 * and redirects back for the classic Blade forms.
 */
class ProductImageController extends Controller
{
    public function __construct(private ProductImageService $images) {}

    public function index(Product $product): JsonResponse
    {
        return response()->json($this->payload($product));
    }

    public function store(StoreProductImagesRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        foreach ($request->file('images') as $file) {
            $this->images->store($product, $file, $request->safe()->only(['alt', 'view_type']) + ['is_lifestyle' => $request->boolean('is_lifestyle')]);
        }

        return $this->respond($request, $product, 'Images uploaded.');
    }

    public function update(UpdateProductImageRequest $request, ProductImage $image): JsonResponse|RedirectResponse
    {
        $image->update($request->safe()->except('is_primary'));
        if ($request->boolean('is_primary')) {
            $this->images->makePrimary($image);
        }

        return $this->respond($request, $image->product, 'Image updated.');
    }

    public function reorder(ReorderProductImagesRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->images->reorder($product, $request->validated('ids'));

        return $this->respond($request, $product, 'Order saved.');
    }

    public function destroy(Request $request, ProductImage $image): JsonResponse|RedirectResponse
    {
        $product = $image->product;
        $image->delete();
        if ($image->is_primary && $next = $product->galleryImages()->first()) {
            $this->images->makePrimary($next);
        }

        return $this->respond($request, $product, 'Image removed.');
    }

    public function storeSpinSet(StoreSpinSetRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->images->storeSpinArchive($product, $request->file('archive'));

        return $this->respond($request, $product, '360° archive uploaded — frames are being prepared.');
    }

    public function storeModel(StoreProductModelRequest $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->images->storeModel($product, $request->file('model'));

        return $this->respond($request, $product, '3D model uploaded — the 360° view now shows it.');
    }

    public function destroyModel(Request $request, Product $product): JsonResponse|RedirectResponse
    {
        $this->images->removeModel($product);

        return $this->respond($request, $product, '3D model removed.');
    }

    private function respond(Request $request, Product $product, string $message): JsonResponse|RedirectResponse
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message] + $this->payload($product->fresh()))
            : back()->with('success', $message);
    }

    private function payload(Product $product): array
    {
        $images = $product->images()->get();

        return [
            'images' => $images->map(fn (ProductImage $image) => (new ProductImageResource($image))->resolve() + [
                'isLifestyle' => $image->is_lifestyle,
                'sortOrder' => $image->sort_order,
                'processed' => $image->renditions !== null,
                'updateUrl' => route('admin.product-images.update', $image),
                'deleteUrl' => route('admin.product-images.destroy', $image),
            ])->values(),
            'spinSet' => $product->spinSet ? ['frameCount' => $product->spinSet->frame_count, 'frames' => $product->spinSet->urls()] : null,
            'model3d' => $product->model3dUrl(),
            'model3dSource' => $product->model3dSource(),
            'sku' => $product->sku,
        ];
    }
}
