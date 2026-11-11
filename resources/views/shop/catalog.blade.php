@php
    $facetLabels = ['gender' => 'Gender', 'frame_type' => 'Frame Type', 'shape' => 'Frame Shape', 'brand' => 'Brand', 'material' => 'Material'];
    $valueLabel = fn (string $facet, string $value) => match ($facet) {
        'gender' => config('store.genders.'.$value, ucfirst($value)),
        'frame_type' => config('store.frame_types.'.$value, $value),
        default => $value,
    };
    $baseParams = array_filter(['category' => $category, 'q' => $search ?: null, 'sort' => $sort !== 'featured' ? $sort : null]);
    $action = request()->routeIs('search') ? route('search') : route('eyeglasses');
@endphp
<x-layouts.app :title="$search !== '' ? 'Search: '.$search : 'Eyeglasses & Sunglasses'" description="Browse precision titanium, rimless and bio-acetate frames. Filter by shape, frame type, brand, material and price.">
    <section class="mx-auto max-w-7xl px-5 py-12" x-data="{ filters: false }">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <x-site.section-head level="1" :eyebrow="$eyebrow" :title="$search !== '' ? '“'.$search.'”' : $title" :sub="$products->total().' '.Str::plural('frame', $products->total()).' · lenses surfaced to ±0.01 diopter accuracy'" />
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex gap-1 rounded-full border border-border bg-card p-1 text-xs">
                    @foreach (['' => 'All'] + config('store.categories') as $key => $label)
                        <a href="{{ route('eyeglasses', array_filter(['category' => $key ?: null, 'q' => $search ?: null])) }}" @class(['rounded-full px-4 py-1.5', 'bg-primary text-primary-foreground' => ($category ?? '') === $key])>{{ $label }}</a>
                    @endforeach
                </div>
                <button type="button" @click="filters = true" class="btn-ghost btn-sm lg:hidden"><x-lucide-sliders-horizontal class="h-3.5 w-3.5" />Filters @if ($activeFilterCount)({{ $activeFilterCount }})@endif</button>
            </div>
        </div>

        <div class="mt-10 grid gap-8 lg:grid-cols-[240px_1fr]">
            {{-- Filters: sidebar on desktop, slide-over on mobile --}}
            <div :class="filters ? 'fixed inset-0 z-50 overflow-y-auto bg-background p-5' : 'hidden lg:block'">
                <form method="GET" action="{{ $action }}" id="catalog-filters" class="space-y-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold">Filters</h2>
                        <div class="flex items-center gap-3">
                            @if ($activeFilterCount)<a href="{{ $action.'?'.http_build_query(array_filter(['category' => $category, 'q' => $search ?: null])) }}" class="text-xs underline">Clear all</a>@endif
                            <button type="button" class="lg:hidden" @click="filters = false" aria-label="Close filters"><x-lucide-x class="h-4 w-4" /></button>
                        </div>
                    </div>
                    @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                    @if ($search !== '')<input type="hidden" name="q" value="{{ $search }}">@endif

                    @foreach ($facetLabels as $facet => $label)
                        @continue($facets[$facet]->isEmpty())
                        <fieldset class="border-t border-border pt-4" x-data="{ open: true }">
                            <legend class="w-full"><button type="button" @click="open = !open" class="flex w-full items-center justify-between text-xs font-semibold uppercase tracking-wider">{{ $label }}<x-lucide-chevron-down class="h-3.5 w-3.5 transition" ::class="open && 'rotate-180'" /></button></legend>
                            <div x-show="open" x-collapse class="mt-3 space-y-2">
                                @foreach ($facets[$facet] as $value => $total)
                                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                                        <input type="checkbox" name="{{ $facet }}[]" value="{{ $value }}" @checked(in_array($value, $selected[$facet], true)) onchange="this.form.requestSubmit()" class="accent-[var(--obsidian)]">
                                        <span class="flex-1">{{ $valueLabel($facet, $value) }}</span><span class="text-xs text-muted-foreground">{{ $total }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <fieldset class="border-t border-border pt-4">
                        <legend class="text-xs font-semibold uppercase tracking-wider">Price (USD)</legend>
                        <div class="mt-3 flex items-center gap-2">
                            <label class="sr-only" for="price-min">Minimum price</label>
                            <input id="price-min" type="number" name="price_min" min="0" step="10" value="{{ $priceMin }}" placeholder="{{ (int) $priceBounds['min'] }}" class="input py-1.5">
                            <span class="text-muted-foreground">–</span>
                            <label class="sr-only" for="price-max">Maximum price</label>
                            <input id="price-max" type="number" name="price_max" min="0" step="10" value="{{ $priceMax }}" placeholder="{{ (int) $priceBounds['max'] }}" class="input py-1.5">
                        </div>
                        <button class="btn btn-sm mt-3 w-full">Apply</button>
                    </fieldset>
                </form>
            </div>

            <div>
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2">
                        @foreach ($selected as $facet => $values)
                            @foreach ($values as $value)
                                @php($params = $baseParams + collect($selected)->map(fn ($v, $k) => $k === $facet ? array_values(array_diff($v, [$value])) : $v)->filter()->all() + array_filter(['price_min' => $priceMin, 'price_max' => $priceMax]))
                                <a href="{{ $action.'?'.http_build_query($params) }}" class="flex items-center gap-1 rounded-full border border-border bg-card px-3 py-1 text-xs hover:border-bronze">{{ $valueLabel($facet, $value) }}<x-lucide-x class="h-3 w-3" /></a>
                            @endforeach
                        @endforeach
                    </div>
                    <label class="flex items-center gap-2 text-xs">Sort by
                        <select name="sort" form="catalog-filters" onchange="this.form.requestSubmit()" class="input w-auto py-1.5 text-xs">
                            @foreach (config('store.catalog_sorts') as $key => $label)<option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @forelse ($products as $product)
                        <x-site.product-card :product="$product" />
                    @empty
                        <div class="col-span-full rounded-2xl border border-border bg-card p-10 text-center text-sm text-muted-foreground">
                            No frames match these filters. <a href="{{ $action.'?'.http_build_query(array_filter(['category' => $category])) }}" class="underline">Clear filters</a>
                        </div>
                    @endforelse
                </div>
                {{ $products->links() }}
            </div>
        </div>
    </section>
    <x-site.newsletter :section="$newsletter" />
</x-layouts.app>
