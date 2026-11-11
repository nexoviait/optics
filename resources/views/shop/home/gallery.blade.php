<section class="bg-obsidian py-24 text-on-dark">
    <div class="mx-auto max-w-7xl px-5">
        <div class="text-center">
            <x-site.badge dark>{{ $c['badge'] ?? '' }}</x-site.badge>
            <h2 class="mx-auto mt-6 max-w-3xl text-3xl font-medium uppercase md:text-5xl">{{ $c['title'] ?? '' }}</h2>
            <p class="mx-auto mt-4 max-w-2xl text-sm text-on-dark-muted">{{ $c['body'] ?? '' }}</p>
        </div>
        <div class="mt-14 grid gap-6 lg:grid-cols-2">
            @foreach ($c['cards'] ?? [] as $card)
                <article class="overflow-hidden rounded-2xl border border-on-dark/10 bg-obsidian-soft">
                    <div class="relative">
                        <img src="{{ asset($card['image'] ?? 'images/gallery.jpg') }}" alt="{{ $card['title'] ?? '' }}" loading="lazy" width="1280" height="912" class="aspect-[4/3] w-full object-cover">
                        <span class="eyebrow absolute left-4 top-4 rounded bg-obsidian/70 px-2 py-1 text-[0.55rem]">{{ $card['tag'] ?? '' }}</span>
                        <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-obsidian to-transparent p-5">
                            <p class="eyebrow text-[0.55rem] text-on-dark-muted">{{ $card['sub'] ?? '' }}</p>
                            <div class="mt-1 flex items-end justify-between"><h3 class="text-xl font-semibold">{{ $card['title'] ?? '' }}</h3><span class="font-mono text-xs text-on-dark-muted">{{ $card['meta'] ?? '' }}</span></div>
                        </div>
                    </div>
                    <div class="p-5">
                        <p class="text-sm text-on-dark-muted">{{ $card['body'] ?? '' }}</p>
                        <div class="mt-5 flex items-center justify-between text-xs">
                            <span class="text-on-dark-muted">{{ $card['foot'] ?? '' }}</span>
                            <a href="{{ url($card['url'] ?? '/') }}" class="rounded bg-ivory px-3 py-1.5 text-obsidian">{{ $card['cta'] ?? 'View' }} →</a>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
        @if (! empty($c['facts']))
            <div class="mt-8 grid grid-cols-2 gap-4 rounded-xl border border-on-dark/10 p-6 text-center md:grid-cols-4">
                @foreach ($c['facts'] as $fact)
                    <div><div class="text-sm font-semibold text-bronze">{{ $fact['title'] ?? '' }}</div><div class="eyebrow mt-1 text-[0.55rem] text-on-dark-muted">{{ $fact['sub'] ?? '' }}</div></div>
                @endforeach
            </div>
        @endif
    </div>
</section>
