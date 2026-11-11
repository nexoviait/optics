@php
    $nav = [
        ['route' => 'eyeglasses', 'label' => 'Eyeglasses'],
        ['route' => 'sunglasses', 'label' => 'Sunglasses'],
        ['route' => 'visionmatch', 'label' => 'Find My Frames', 'icon' => true],
        ['route' => 'science', 'label' => 'Our Science'],
    ];
@endphp
<header class="sticky top-0 z-40 border-b border-border bg-background/90 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-7xl items-center gap-6 px-5">
        <x-site.logo />
        <nav class="hidden flex-1 items-center gap-6 text-sm lg:flex">
            @foreach ($nav as $item)
                <a href="{{ route($item['route']) }}" @class([
                    'flex items-center gap-1.5 border-b-2 py-1 transition-colors hover:text-foreground',
                    'border-foreground font-medium text-foreground' => request()->routeIs($item['route']),
                    'border-transparent text-foreground/80' => ! request()->routeIs($item['route']),
                ])>
                    @isset($item['icon'])<x-lucide-scan-face class="h-4 w-4" />@endisset
                    {{ $item['label'] }}
                </a>
            @endforeach
        </nav>

        {{-- Search with live suggestions --}}
        <form method="GET" action="{{ route('search') }}" role="search" class="relative ml-auto hidden w-64 md:block xl:w-72"
              x-data="siteSearch('{{ route('search.suggest') }}', @js(request('q', '')))" @click.outside="open = false" @keydown.escape="open = false">
            <label for="site-search" class="sr-only">Search frames</label>
            <x-lucide-search class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
            <input id="site-search" name="q" type="search" autocomplete="off" x-model="q" @input="input" @focus="results.length && (open = true)"
                   @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter="choose($event)"
                   placeholder="Search frames, shapes, brands…" class="input rounded-full bg-card py-2 pl-9 text-xs"
                   role="combobox" aria-autocomplete="list" aria-controls="search-suggestions" :aria-expanded="open">
            <div x-show="open" x-cloak id="search-suggestions" role="listbox" class="absolute inset-x-0 top-full mt-2 overflow-hidden rounded-xl border border-border bg-card shadow-lg">
                <template x-for="(item, i) in results" :key="item.url">
                    <a :href="item.url" role="option" :aria-selected="active === i" :class="active === i && 'bg-secondary'" class="flex items-center gap-3 px-3 py-2 hover:bg-secondary">
                        <img :src="item.image" alt="" class="h-9 w-12 rounded object-cover">
                        <span class="min-w-0 flex-1"><span class="block truncate text-sm" x-text="item.name"></span><span class="block truncate text-[0.65rem] text-muted-foreground" x-text="item.meta"></span></span>
                        <span class="font-mono text-xs" x-text="item.price"></span>
                    </a>
                </template>
                <p x-show="!results.length" class="px-3 py-3 text-xs text-muted-foreground">No frames match “<span x-text="q"></span>”.</p>
                <a x-show="results.length" :href="allUrl" class="block border-t border-border px-3 py-2 text-xs font-medium">See all results →</a>
            </div>
        </form>

        <div class="flex items-center gap-2 md:ml-0">
            <a href="{{ route('search') }}" class="p-2 md:hidden" aria-label="Search"><x-lucide-search class="h-4 w-4" /></a>
            @auth
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="hidden text-xs font-medium xl:inline">Admin</a>
                @endif
                <a href="{{ route('account') }}" aria-label="My account" class="p-2"><x-lucide-user class="h-4 w-4" /></a>
            @else
                <a href="{{ route('login') }}" class="px-1 text-xs font-medium">Sign in</a>
            @endauth
            <a href="{{ route('wishlist.index') }}" aria-label="Wishlist" class="relative p-2">
                <x-lucide-heart class="h-4 w-4" />
                <span x-data x-show="$store.wishlist.count > 0" x-text="$store.wishlist.count" x-cloak
                      class="absolute -right-0.5 top-0 grid h-4 min-w-4 place-items-center rounded-full bg-bronze px-1 text-[0.6rem] font-bold text-obsidian">{{ $wishlistCount }}</span>
            </a>
            <a href="{{ route('cart.index') }}" aria-label="Bag" class="relative grid h-9 w-9 place-items-center rounded-full bg-primary text-primary-foreground">
                <x-lucide-shopping-bag class="h-4 w-4" />
                <span x-data x-show="$store.cart.count > 0" x-text="$store.cart.count" x-cloak
                      class="absolute -right-1 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-bronze px-1 text-[0.6rem] font-bold text-obsidian">{{ $cartCount }}</span>
            </a>
        </div>
    </div>
    <nav class="flex gap-5 overflow-x-auto border-t border-border px-5 py-2 text-xs lg:hidden">
        @foreach ($nav as $item)
            <a href="{{ route($item['route']) }}" @class(['whitespace-nowrap', 'font-semibold' => request()->routeIs($item['route'])])>{{ $item['label'] }}</a>
        @endforeach
    </nav>
</header>
