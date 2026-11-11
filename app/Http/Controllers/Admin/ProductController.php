<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $category = $request->query('category');
        $search = trim((string) $request->query('q'));

        return view('admin.products.index', [
            'products' => Product::query()->ordered()
                ->when($category, fn ($q) => $q->where('category', $category))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%")))
                ->paginate(20)->withQueryString(),
            'category' => $category,
            'search' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product([
            'status' => 'active', 'category' => 'eyeglasses', 'price' => 540, 'cost' => 140,
            'stock' => 20, 'low_stock_threshold' => 10, 'finishes' => ['#C9A887'],
            'gender' => 'unisex', 'frame_type' => 'full_rim',
        ])]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::query()->create($this->payload($request));
        ActivityLog::record("Product created: {$product->name}", $product);

        return redirect()->route('admin.products.edit', $product)->with('success', "{$product->name} saved.");
    }

    public function edit(Product $product): View
    {
        return view('admin.products.form', [
            'product' => $product->load('images'),
            'unitsSold' => $product->unitsSold(),
            'revenue' => (float) $product->orderItems()->whereHas('order', fn ($q) => $q->where('status', '!=', 'Cancelled'))->selectRaw('COALESCE(SUM(unit_price * qty), 0) as total')->value('total'),
            'reviews' => $product->reviews()->latest()->get(),
            'activity' => ActivityLog::query()->with('user')->where('subject_type', $product->getMorphClass())->where('subject_id', $product->id)->latest()->take(10)->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $before = (float) $product->price;
        $product->update($this->payload($request, $product));

        $change = $before !== (float) $product->price ? sprintf(' (price $%s → $%s)', number_format($before), number_format((float) $product->price)) : '';
        ActivityLog::record("Product updated{$change}", $product);

        return back()->with('success', 'Changes saved.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        if ($product->image && ! Str::startsWith($product->image, 'images/')) {
            Storage::disk('public')->delete($product->image);
        }
        if ($product->try_on_image) {
            Storage::disk('public')->delete($product->try_on_image);
        }
        $name = $product->name;
        $product->delete();
        ActivityLog::record("Product deleted: {$name}");

        return redirect()->route('admin.products.index')->with('success', "{$name} deleted.");
    }

    public function destroyReview(ProductReview $review): RedirectResponse
    {
        $review->delete();

        return back()->with('success', 'Review removed.');
    }

    private function payload(ProductRequest $request, ?Product $product = null): array
    {
        $data = $request->safe()->except(['image', 'try_on_image', 'remove_try_on_image']);
        // Keep existing URLs stable: only derive a slug for new products.
        $data['slug'] = ($data['slug'] ?? null) ?: ($product?->slug ?? Str::slug($data['name']));

        if ($request->hasFile('image')) {
            if ($product?->image && ! Str::startsWith($product->image, 'images/')) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        if ($request->hasFile('try_on_image') || $request->boolean('remove_try_on_image')) {
            if ($product?->try_on_image) {
                Storage::disk('public')->delete($product->try_on_image);
            }
            $data['try_on_image'] = $request->hasFile('try_on_image')
                ? $request->file('try_on_image')->store('products/try-on', 'public')
                : null;
        }

        return $data;
    }
}
