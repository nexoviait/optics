{{--
    Root view for Inertia (React) pages. They render inside the normal storefront
    layout, so the Blade/Alpine header, footer, cart badge, search and toasts are
    shared with every other page. Page props may carry `meta` for SEO: title,
    description, a preload image (the LCP photo) and JSON-LD.
--}}
@php($meta = $page['props']['meta'] ?? [])
<x-layouts.app :title="$meta['title'] ?? null" :description="$meta['description'] ?? null" :inertia="true">
    <x-slot:head>
        @if (! empty($meta['preloadImage']))
            <link rel="preload" as="image" fetchpriority="high"
                  href="{{ $meta['preloadImage']['href'] }}"
                  @if (! empty($meta['preloadImage']['srcset'])) imagesrcset="{{ $meta['preloadImage']['srcset'] }}" imagesizes="{{ $meta['preloadImage']['sizes'] }}" @endif
                  @if (! empty($meta['preloadImage']['type'])) type="{{ $meta['preloadImage']['type'] }}" @endif>
        @endif
        @if (! empty($meta['jsonLd']))
            {{-- Keyed so the React page's <Head> replaces it when the product changes without a reload. --}}
            <script type="application/ld+json" data-inertia="product-jsonld">{!! json_encode($meta['jsonLd'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
        @endif
        @viteReactRefresh
        @vite('resources/js/inertia.tsx')
        @inertiaHead
    </x-slot:head>

    @inertia
</x-layouts.app>
