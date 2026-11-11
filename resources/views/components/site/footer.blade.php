@php
    $quick = [
        ['eyeglasses', 'Eyeglasses', 'glasses'],
        ['sunglasses', 'Sunglasses', 'sun'],
        ['visionmatch', 'Find My Frames', 'scan-face'],
        ['science', 'Our Science', 'flask-conical'],
        ['orders.track', 'Track Order', 'package-search'],
    ];
    $shop = [
        ['Eyeglasses', route('eyeglasses', ['category' => 'eyeglasses'])],
        ['Sunglasses', route('sunglasses')],
        ['Men', route('eyeglasses', ['gender' => ['men']])],
        ['Women', route('eyeglasses', ['gender' => ['women']])],
        ['Rimless', route('eyeglasses', ['frame_type' => ['rimless']])],
        ['Home Trial Box', route('sunglasses').'#home-trial'],
        ['My Wishlist', route('wishlist.index')],
    ];
    $socials = collect(config('store.social_networks'))->filter(fn ($label, $key) => ! empty($store['social_'.$key]));
@endphp
<footer class="bg-obsidian text-on-dark">
    <div class="mx-auto max-w-7xl px-5 py-14">
        <div class="flex flex-col justify-between gap-6 md:flex-row md:items-center">
            <div class="flex items-center gap-6"><x-site.logo dark /><p class="max-w-md text-sm text-on-dark-muted">{{ $store['footer_blurb'] ?? '' }}</p></div>
            @if (! empty($store['ateliers']))<p class="eyebrow text-on-dark-muted">Global ateliers: {{ $store['ateliers'] }}</p>@endif
        </div>
        <div class="mt-10 flex flex-wrap items-center gap-2 rounded-xl border border-on-dark/10 bg-obsidian-soft p-3">
            <span class="eyebrow mr-auto text-bronze">Quick navigation</span>
            @foreach ($quick as [$route, $label, $icon])
                <a href="{{ route($route) }}" class="flex items-center gap-1.5 rounded-md border border-on-dark/10 px-3 py-1.5 text-xs hover:border-bronze hover:text-bronze">
                    @svg('lucide-'.$icon, 'h-3.5 w-3.5'){{ $label }}
                </a>
            @endforeach
        </div>
        <div class="mt-12 grid gap-10 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <h4 class="eyebrow border-l-2 border-bronze pl-2 text-bronze">Shop</h4>
                <ul class="mt-4 space-y-2.5 text-sm text-on-dark-muted">
                    @foreach ($shop as [$label, $url])<li><a href="{{ $url }}" class="hover:text-on-dark">{{ $label }}</a></li>@endforeach
                </ul>
            </div>
            @foreach (config('store.footer_groups') as $group => $heading)
                <div>
                    <h4 class="eyebrow border-l-2 border-bronze pl-2 text-bronze">{{ $heading }}</h4>
                    <ul class="mt-4 space-y-2.5 text-sm text-on-dark-muted">
                        @foreach ($footerPages->get($group, []) as $page)
                            <li><a href="{{ route('pages.show', $page) }}" class="hover:text-on-dark">{{ $page->title }}</a></li>
                        @endforeach
                        @if ($group === 'company')
                            <li><a href="{{ route('science') }}" class="hover:text-on-dark">Our Science</a></li>
                            <li><a href="{{ route('account') }}" class="hover:text-on-dark">My Account & Orders</a></li>
                        @endif
                    </ul>
                </div>
            @endforeach
        </div>
        <div class="mt-12 flex flex-wrap items-center gap-2 border-t border-on-dark/10 pt-6">
            @foreach ([['badge-check', 'ISO 13485 Certified'], ['lock', '256-bit Encrypted Rx'], ['truck', 'Global Courier Prepaid']] as [$icon, $label])
                <span class="eyebrow flex items-center gap-1.5 rounded border border-on-dark/10 px-3 py-1.5 text-on-dark-muted">@svg('lucide-'.$icon, 'h-3 w-3'){{ $label }}</span>
            @endforeach
            <div class="ml-auto flex flex-wrap items-center gap-2">
                @if (! empty($store['app_store_url']))<a href="{{ $store['app_store_url'] }}" rel="noopener" target="_blank" class="rounded border border-on-dark/20 px-3 py-1.5 text-xs hover:border-bronze">App Store</a>@endif
                @if (! empty($store['play_store_url']))<a href="{{ $store['play_store_url'] }}" rel="noopener" target="_blank" class="rounded border border-on-dark/20 px-3 py-1.5 text-xs hover:border-bronze">Google Play</a>@endif
                @foreach ($socials as $key => $label)
                    <a href="{{ $store['social_'.$key] }}" rel="noopener" target="_blank" class="rounded-full border border-on-dark/20 px-3 py-1.5 text-xs hover:border-bronze hover:text-bronze">{{ $label }}</a>
                @endforeach
            </div>
        </div>
        <p class="mt-8 text-xs text-on-dark-muted">© {{ date('Y') }} {{ $store['store_name'] ?? 'REFRA' }}. All rights reserved. · {{ $store['registered_address'] ?? '' }}</p>
    </div>
</footer>
