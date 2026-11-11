<section class="mx-auto max-w-7xl px-5 py-24">
    <x-site.section-head center :eyebrow="$c['eyebrow'] ?? ''" :title="$c['title'] ?? ''" :sub="$c['body'] ?? null" />
    <div class="mt-12 grid gap-5 md:grid-cols-3">
        @foreach ($c['cards'] ?? [] as $card)
            <div class="flex flex-col rounded-2xl border border-border bg-card p-6">
                <div class="grid h-11 w-11 place-items-center rounded-lg bg-bronze-soft">@svg('lucide-'.($card['icon'] ?? 'sparkles'), 'h-5 w-5')</div>
                <h3 class="mt-5 font-semibold">{{ $card['title'] ?? '' }}</h3>
                <p class="mt-2 flex-1 text-sm text-muted-foreground">{{ $card['body'] ?? '' }}</p>
                <div class="mt-6 flex justify-between border-t border-border pt-4 text-xs">
                    <span class="eyebrow text-[0.55rem] text-muted-foreground">{{ $card['note'] ?? '' }}</span>
                    <a href="{{ url($card['url'] ?? '/') }}" class="font-medium">{{ $card['cta'] ?? '' }}</a>
                </div>
            </div>
        @endforeach
    </div>
</section>
