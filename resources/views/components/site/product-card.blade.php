@props(['product'])
<div class="group flex flex-col rounded-2xl border border-border bg-card p-3 transition hover:-translate-y-1 hover:shadow-[0_20px_40px_-24px_var(--obsidian)]">
    <div class="flex items-center justify-between gap-2">
        <span class="eyebrow truncate rounded-full bg-sage-soft px-2 py-1 text-[0.55rem]">{{ $product->badge }}</span>
        <div class="flex items-center gap-2">
            @if ($product->stock < 1)
                <span class="eyebrow text-[0.55rem] text-destructive">Sold out</span>
            @elseif ($product->isLowStock())
                <span class="eyebrow text-[0.55rem] text-muted-foreground">Only {{ $product->stock }} left</span>
            @endif
            <button type="button" x-data="wishlistButton(@js(route('wishlist.toggle', $product)), @js(in_array($product->id, $wishlistIds, true)))" @click="toggle" :aria-pressed="saved"
                    :aria-label="(saved ? 'Remove ' : 'Save ') + @js($product->name) + (saved ? ' from wishlist' : ' to wishlist')" class="rounded-full p-1 hover:bg-secondary">
                <x-lucide-heart class="h-4 w-4" ::class="saved ? 'fill-current text-destructive' : 'text-muted-foreground'" />
            </button>
        </div>
    </div>
    <a href="{{ route('products.show', $product) }}" draggable="false" @dragstart.prevent class="relative mt-3 block overflow-hidden rounded-xl bg-secondary" aria-label="{{ $product->name }}">
        @php($hasSpinPhotos = count($product->spinUrls()) > 1)
        @if ($hasSpinPhotos)
            <span class="eyebrow pointer-events-none absolute left-2 top-2 z-10 rounded bg-primary px-1.5 py-0.5 text-[0.5rem] text-primary-foreground">360° View</span>
        @endif
        @if ($product->isDiscounted())
            <span class="pointer-events-none absolute right-2 top-2 z-10 rounded bg-bronze px-1.5 py-0.5 text-[0.6rem] font-semibold text-obsidian">-{{ $product->discountPercent() }}%</span>
        @endif
        @if ($hasSpinPhotos)
            {{-- Turntable photography: cards stay still (front view); shoppers can drag to rotate. --}}
            <x-site.spin-viewer :product="$product" :controls="false" :autoplay="false" :hover-only="true" />
        @else
            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }} — {{ $product->color }}" loading="lazy" decoding="async" draggable="false"
                 class="aspect-[4/3] w-full bg-white object-contain p-2 transition duration-500 group-hover:scale-105">
        @endif
    </a>
    <div class="mt-3 flex items-center justify-between">
        @if ($hasSpinPhotos)
            <span class="eyebrow flex items-center gap-1 text-[0.55rem] text-muted-foreground"><x-lucide-rotate-cw class="h-3 w-3" />Drag to rotate</span>
        @else
            <span class="eyebrow text-[0.55rem] text-muted-foreground">{{ $product->color_name ?: $product->color }}</span>
        @endif
        <div class="flex gap-1">
            @foreach ($product->finishColors() as $color)
                <span class="h-2.5 w-2.5 rounded-full border border-border" style="background: {{ $color }}"></span>
            @endforeach
        </div>
    </div>
    <a href="{{ route('products.show', $product) }}" class="mt-3">
        @if ($product->brand)<p class="eyebrow text-[0.55rem] text-muted-foreground">{{ $product->brand }}</p>@endif
        <h3 class="text-sm font-semibold">{{ $product->name }}</h3>
        <p class="text-xs text-muted-foreground">{{ $product->styleLine() ?: $product->series }}</p>
    </a>
    <dl class="mt-3 space-y-1 border-t border-border pt-3 text-[0.7rem]">
        @foreach (['Shape' => $product->shape, 'Color' => $product->color, 'Material' => $product->material] as $k => $v)
            <div class="flex justify-between gap-2"><dt class="text-muted-foreground">{{ $k }}</dt><dd class="text-right">{{ $v }}</dd></div>
        @endforeach
    </dl>
    <div class="mt-auto flex items-end justify-between gap-2 pt-4">
        <div>
            <div class="eyebrow text-[0.5rem] text-muted-foreground">{{ $product->lens_tag }}</div>
            <div class="flex flex-wrap items-baseline gap-x-1.5">
                <span class="font-display font-semibold">${{ number_format((float) $product->price) }}</span>
                @if ($product->isDiscounted())<span class="text-xs text-muted-foreground line-through">${{ number_format((float) $product->compare_at_price) }}</span>@endif
            </div>
            @if ($product->offer_text)<div class="text-[0.6rem] text-sage">{{ $product->offer_text }}</div>@endif
        </div>
        <button type="button" @click="addToBag({{ $product->id }}, @js($product->color))" @disabled($product->stock < 1)
                class="flex shrink-0 items-center gap-1.5 rounded-md bg-primary px-3 py-1.5 text-xs text-primary-foreground transition hover:bg-obsidian-soft disabled:opacity-50">
            <x-lucide-shopping-bag class="h-3 w-3" /> Add to Bag
        </button>
    </div>
</div>
