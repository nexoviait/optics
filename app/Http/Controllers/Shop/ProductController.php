<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductImageResource;
use App\Models\FrameSize;
use App\Models\LensOption;
use App\Models\PowerType;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductReview;
use App\Services\Shipping;
use App\Services\Wishlist;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProductController extends Controller
{
    /** Product detail page, rendered by the React page resources/js/Pages/Product/Show.tsx. */
    public function show(Request $request, Product $product, Wishlist $wishlist): InertiaResponse
    {
        abort_unless($product->status === 'active', 404);
        $product->load(['galleryImages', 'lifestyleImages']);

        // Count one view per product per session.
        $viewed = $request->session()->get('viewed_products', []);
        if (! in_array($product->id, $viewed, true)) {
            $product->increment('views');
            $request->session()->push('viewed_products', $product->id);
        }

        $reviews = $product->reviews()->published()->latest()->get();
        $count = $reviews->count();
        $average = $count ? round($reviews->avg('rating'), 1) : null;
        $images = $this->galleryImages($product);
        $spin = $product->spinUrls();
        $wishlistIds = $wishlist->ids();

        // Similar frames first (same category and shape), then the rest of the catalog.
        $related = Product::query()->active()->whereKeyNot($product->id)
            ->when($product->variant_group, fn ($q) => $q->where(fn ($w) => $w->whereNull('variant_group')->orWhere('variant_group', '!=', $product->variant_group)))
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$product->category])
            ->orderByRaw('CASE WHEN shape = ? THEN 0 ELSE 1 END', [$product->shape])
            ->ordered()->take(4)->get();

        return Inertia::render('Product/Show', [
            'product' => [
                'id' => $product->id,
                'slug' => $product->slug,
                'name' => $product->name,
                'brand' => $product->brand,
                'brandUrl' => $product->brand ? route('eyeglasses', ['brand' => [$product->brand]]) : null,
                'category' => $product->category,
                'categoryUrl' => route('eyeglasses', ['category' => $product->category]),
                'ageGroup' => $product->age_group,
                'styleLine' => $product->styleLine(),
                'price' => (float) $product->price,
                'compareAtPrice' => $product->isDiscounted() ? (float) $product->compare_at_price : null,
                'discountPercent' => $product->discountPercent(),
                'offerText' => $product->offer_text,
                'description' => $product->description,
                'color' => $product->color,
                'colorName' => $product->color_name ?: $product->color,
                'finishes' => $product->finishColors(),
                'stock' => $product->stock,
                'isLowStock' => $product->isLowStock(),
                'tryOn' => $product->tryOnStyle(),
                'specs' => $this->specGroups($product),
                'wishlisted' => in_array($product->id, $wishlistIds, true),
                'urls' => [
                    'wishlist' => route('wishlist.toggle', $product),
                    'review' => route('products.reviews.store', $product),
                    'cart' => route('cart.store'),
                ],
            ],
            'images' => ProductImageResource::collection($images)->resolve(),
            'spinSet' => count($spin) ? ['frameCount' => count($spin), 'frames' => $spin] : null,
            // A 3D model (.glb) takes priority over photos in the 360° view.
            'model3d' => $product->model3dUrl(),
            'model3dIos' => $product->model3dIosUrl(),
            // 3D try-ons (model photo and live camera).
            'liveTryOn' => $product->liveTryOnSettings(),
            'lifestyle' => $product->lifestyleImages->map(fn ($i) => ['src' => $i->url(), 'alt' => $i->alt ?: 'Styling '.$product->name])->values(),
            'variants' => $product->variants()->map(fn (Product $v) => [
                'slug' => $v->slug,
                'url' => route('products.show', $v),
                'label' => $v->color_name ?: $v->color,
                'swatch' => $v->finishColors()[0],
                'current' => $v->is($product),
            ])->values(),
            'sizes' => FrameSize::ordered()->map(fn ($s) => ['code' => $s->code, 'width' => $s->width, 'lens' => $s->lens_width, 'bridge' => $s->bridge, 'temple' => $s->temple, 'face' => $s->face])->values(),
            'powerTypes' => PowerType::query()->active()->get()->map(fn ($p) => ['code' => $p->code, 'label' => $p->label, 'description' => $p->description, 'price' => (float) $p->price, 'needsLens' => (bool) $p->needs_lens, 'needsRx' => (bool) $p->needs_rx])->values(),
            'lensOptions' => LensOption::query()->active()->get()->map(fn ($l) => ['code' => $l->code, 'label' => $l->label, 'description' => $l->description, 'price' => (float) $l->price, 'badge' => $l->badge, 'features' => $l->features ?? []])->values(),
            'reviews' => [
                'items' => $reviews->map(fn (ProductReview $r) => [
                    'id' => $r->id,
                    'name' => $r->reviewer_name,
                    'rating' => $r->rating,
                    'title' => $r->title,
                    'body' => $r->body,
                    'verified' => $r->verified_buyer,
                    'date' => $r->created_at->toIso8601String(),
                    'dateLabel' => $r->created_at->format('j M Y'),
                ])->values(),
                'count' => $count,
                'average' => $average,
                'verified' => $reviews->where('verified_buyer', true)->count(),
                'distribution' => collect([5, 4, 3, 2, 1])->map(fn (int $r) => [
                    'rating' => $r,
                    'count' => $reviews->where('rating', $r)->count(),
                    'percent' => $count ? (int) round(100 * $reviews->where('rating', $r)->count() / $count) : 0,
                ])->values(),
                'hasReviewed' => $request->user() ? $reviews->contains('user_id', $request->user()->id) : false,
            ],
            'related' => $related->map(fn (Product $p) => $this->card($p, $wishlistIds))->values(),
            'regions' => collect(config('store.delivery_regions'))->map(fn ($r, $code) => ['code' => $code, 'name' => $r['name']])->values(),
            'galleryLayout' => config('store.gallery_layout', 'grid'),
            'meta' => $this->meta($product, $images, $count, $average),
        ]);
    }

    /**
     * Gallery photos, primary first. Products without uploaded gallery rows fall
     * back to their main image so the gallery is never empty.
     *
     * @return Collection<int, ProductImage>
     */
    private function galleryImages(Product $product): Collection
    {
        $images = $product->galleryImages;
        if ($images->isEmpty()) {
            $images = collect([new ProductImage(['path' => $product->image ?: 'images/frame-apex.jpg', 'alt' => $product->name, 'view_type' => 'angle', 'is_primary' => true])]);
        }

        return $images->values();
    }

    /** @return list<array{title: string, rows: list<array{label: string, value: string, href?: string}>}> */
    private function specGroups(Product $product): array
    {
        $groups = [
            'About the Product' => [
                ['Product ID', (string) $product->id], ['SKU', $product->sku], ['Brand Name', $product->brand], ['Model No.', $product->model_no],
                ['Gender', $product->genderLabel()], ['Age Group', $product->age_group], ['Collection', $product->series],
            ],
            'Style Details' => [
                ['Frame Type', $product->frameTypeLabel(), route('eyeglasses', ['frame_type' => [$product->frame_type]])],
                ['Frame Shape', $product->shape, route('eyeglasses', ['shape' => [$product->shape]])],
                ['Colour', $product->color],
            ],
            'Material & Build' => [
                ['Frame Material', $product->material], ['Temple Material', $product->temple_material], ['Weight', $product->weight],
            ],
        ];

        return collect($groups)->map(fn (array $rows, string $title) => [
            'title' => $title,
            'rows' => collect($rows)->filter(fn ($row) => filled($row[1]))->map(fn ($row) => array_filter([
                'label' => $row[0], 'value' => (string) $row[1], 'href' => $row[2] ?? null,
            ]))->values()->all(),
        ])->values()->all();
    }

    /** Data for a related-product card on the React page. */
    private function card(Product $p, array $wishlistIds): array
    {
        return [
            'id' => $p->id,
            'url' => route('products.show', $p),
            'name' => $p->name,
            'brand' => $p->brand,
            'badge' => $p->badge,
            'styleLine' => $p->styleLine() ?: $p->series,
            'image' => $p->imageUrl(),
            'price' => (float) $p->price,
            'compareAtPrice' => $p->isDiscounted() ? (float) $p->compare_at_price : null,
            'discountPercent' => $p->discountPercent(),
            'offerText' => $p->offer_text,
            'lensTag' => $p->lens_tag,
            'colorName' => $p->color_name ?: $p->color,
            'finishes' => $p->finishColors(),
            'specs' => array_filter(['Shape' => $p->shape, 'Color' => $p->color, 'Material' => $p->material]),
            'stock' => $p->stock,
            'isLowStock' => $p->isLowStock(),
            'wishlisted' => in_array($p->id, $wishlistIds, true),
            'wishlistUrl' => route('wishlist.toggle', $p),
        ];
    }

    /** SEO for the Blade root view: title, description, LCP image preload and Product JSON-LD. */
    private function meta(Product $product, Collection $images, int $reviewCount, ?float $average): array
    {
        $first = $images->first();
        $sources = $first->sources();
        $webp = $sources['webp'];
        $largestJpg = end($sources['jpg']) ?: $product->imageUrl();

        return [
            'title' => trim(($product->brand ? $product->brand.' ' : '').$product->name),
            'description' => $product->description,
            'preloadImage' => $webp
                ? ['href' => end($webp), 'srcset' => collect($webp)->map(fn ($url, $w) => "{$url} {$w}w")->implode(', '), 'sizes' => ProductImageResource::SIZES, 'type' => 'image/webp']
                : ['href' => $largestJpg],
            'jsonLd' => array_filter([
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $product->name,
                'description' => $product->description,
                'sku' => $product->sku,
                'image' => $images->map(fn (ProductImage $i) => end($i->sources()['jpg']) ?: $i->url())->values()->all(),
                'brand' => $product->brand ? ['@type' => 'Brand', 'name' => $product->brand] : null,
                'color' => $product->color,
                'material' => $product->material,
                'offers' => [
                    '@type' => 'Offer',
                    'url' => route('products.show', $product),
                    'priceCurrency' => 'USD',
                    'price' => number_format((float) $product->price, 2, '.', ''),
                    'availability' => $product->stock > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'itemCondition' => 'https://schema.org/NewCondition',
                ],
                'aggregateRating' => $reviewCount ? ['@type' => 'AggregateRating', 'ratingValue' => $average, 'reviewCount' => $reviewCount] : null,
            ]),
        ];
    }

    public function review(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'reviewer_name' => ['required', 'string', 'min:2', 'max:60'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $exists = ProductReview::query()->where('user_id', $request->user()->id)->where('product_id', $product->id)->exists();
        if ($exists) {
            return back()->withErrors(['review' => 'You have already reviewed this frame.']);
        }

        $product->reviews()->create($data + ['user_id' => $request->user()->id]);

        return redirect(route('products.show', $product).'#reviews')->with('success', 'Your review has been saved.');
    }

    public function delivery(Request $request, Shipping $shipping): JsonResponse
    {
        $data = $request->validate([
            'region' => ['required', 'string', Rule::in(array_keys(config('store.delivery_regions')))],
            'postal' => ['required', 'string', 'max:12'],
        ]);

        return response()->json($shipping->estimate($data['postal'], $data['region']));
    }
}
