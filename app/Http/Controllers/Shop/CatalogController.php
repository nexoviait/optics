<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\PageSection;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /** Multi-select facets: request key => product column. */
    private const FACETS = [
        'gender' => 'gender',
        'frame_type' => 'frame_type',
        'shape' => 'shape',
        'brand' => 'brand',
        'material' => 'material',
    ];

    public function eyeglasses(Request $request): View
    {
        $category = in_array($request->query('category'), array_keys(config('store.categories')), true) ? $request->query('category') : null;

        return $this->listing($request, $category, 'The Atelier Catalog', 'Complete architectural archive');
    }

    public function search(Request $request): View
    {
        return $this->listing($request, null, 'Search results', 'Search');
    }

    public function sunglasses(): View
    {
        return view('shop.sunglasses', [
            'products' => Product::query()->active()->ordered()->where('category', 'sunglasses')->get(),
            'newsletter' => PageSection::query()->forPage('home')->where('key', 'newsletter')->first(),
        ]);
    }

    /** Typeahead suggestions for the header search. */
    public function suggest(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (mb_strlen($term) < 2) {
            return response()->json(['products' => []]);
        }

        $products = $this->searchScope(Product::query()->active(), $term)->ordered()->take(6)->get();

        return response()->json([
            'products' => $products->map(fn (Product $p) => [
                'name' => $p->name,
                'meta' => $p->styleLine(),
                'price' => '$'.number_format((float) $p->price),
                'image' => $p->imageUrl(),
                'url' => route('products.show', $p),
            ]),
            'all_url' => route('search', ['q' => $term]),
        ]);
    }

    private function listing(Request $request, ?string $category, string $title, string $eyebrow): View
    {
        $search = trim((string) $request->query('q'));
        $sort = array_key_exists((string) $request->query('sort'), config('store.catalog_sorts')) ? $request->query('sort') : 'featured';
        $selected = collect(self::FACETS)->keys()->mapWithKeys(fn (string $key) => [
            $key => array_values(array_filter((array) $request->query($key, []), 'is_string')),
        ])->all();
        $priceMin = is_numeric($request->query('price_min')) ? (float) $request->query('price_min') : null;
        $priceMax = is_numeric($request->query('price_max')) ? (float) $request->query('price_max') : null;

        // Base scope (category + search) drives the facet counts so options never dead-end.
        $base = Product::query()->active()
            ->when($category, fn (Builder $q) => $q->where('category', $category))
            ->when($search !== '', fn (Builder $q) => $this->searchScope($q, $search));

        $facets = collect(self::FACETS)->map(fn (string $column) => (clone $base)
            ->whereNotNull($column)->where($column, '!=', '')
            ->selectRaw("{$column} as value, COUNT(*) as total")
            ->groupBy($column)->orderBy($column)
            ->pluck('total', 'value'));

        $query = (clone $base)
            ->when($priceMin !== null, fn (Builder $q) => $q->where('price', '>=', $priceMin))
            ->when($priceMax !== null, fn (Builder $q) => $q->where('price', '<=', $priceMax))
            ->withAvg(['reviews as rating' => fn ($q) => $q->where('is_published', true)], 'rating');

        foreach (self::FACETS as $key => $column) {
            if ($selected[$key] !== []) {
                $query->whereIn($column, $selected[$key]);
            }
        }

        match ($sort) {
            'popular' => $query->orderByDesc('views'),
            'newest' => $query->latest(),
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'rating' => $query->orderByDesc('rating'),
            default => $query->ordered(),
        };

        return view('shop.catalog', [
            'title' => $title,
            'eyebrow' => $eyebrow,
            'products' => $query->paginate(24)->withQueryString(),
            'category' => $category,
            'search' => $search,
            'sort' => $sort,
            'facets' => $facets,
            'selected' => $selected,
            'priceMin' => $priceMin,
            'priceMax' => $priceMax,
            'priceBounds' => ['min' => (float) (clone $base)->min('price'), 'max' => (float) (clone $base)->max('price')],
            'activeFilterCount' => collect($selected)->flatten()->count() + ($priceMin !== null ? 1 : 0) + ($priceMax !== null ? 1 : 0),
            'newsletter' => PageSection::query()->forPage('home')->where('key', 'newsletter')->first(),
        ]);
    }

    private function searchScope(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $w) => $w
            ->where('name', 'like', $like)
            ->orWhere('brand', 'like', $like)
            ->orWhere('model_no', 'like', $like)
            ->orWhere('sku', 'like', $like)
            ->orWhere('shape', 'like', $like)
            ->orWhere('material', 'like', $like)
            ->orWhere('color', 'like', $like));
    }
}
