<section class="mx-auto max-w-7xl px-5 pb-24" x-data="{ tab: 'eyeglasses' }">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <x-site.section-head :eyebrow="$c['eyebrow'] ?? ''" :title="$c['title'] ?? ''" />
        <div class="flex rounded-full border border-border bg-card p-1 text-xs">
            @foreach (['eyeglasses' => 'eye', 'sunglasses' => 'sun'] as $cat => $icon)
                <button type="button" @click="tab = '{{ $cat }}'" :class="tab === '{{ $cat }}' && 'bg-primary text-primary-foreground'" class="flex items-center gap-1.5 rounded-full px-4 py-1.5 capitalize">@svg('lucide-'.$icon, 'h-3 w-3'){{ $cat }}</button>
            @endforeach
        </div>
    </div>
    @foreach (['eyeglasses', 'sunglasses'] as $cat)
        @php($shown = $products->where('category', $cat)->concat($products->where('category', '!=', $cat))->take(4))
        <div x-show="tab === '{{ $cat }}'" @if ($cat !== 'eyeglasses') x-cloak @endif class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($shown as $product)
                <x-site.product-card :product="$product" />
            @endforeach
        </div>
    @endforeach
    <div class="mt-10 rounded-2xl bg-secondary px-6 py-12 text-center">
        <p class="eyebrow text-muted-foreground">Complete architectural archive</p>
        <h3 class="mt-3 text-2xl font-medium">{{ $c['archive_title'] ?? '' }}</h3>
        <p class="mx-auto mt-2 max-w-md text-sm text-muted-foreground">{{ $c['archive_body'] ?? '' }}</p>
        <a href="{{ route('eyeglasses') }}" class="mt-6 inline-flex items-center gap-2 rounded-md bg-primary px-5 py-3 text-xs uppercase tracking-widest text-primary-foreground">Explore Complete Atelier Catalog <x-lucide-arrow-up-right class="h-3.5 w-3.5" /></a>
    </div>
</section>
