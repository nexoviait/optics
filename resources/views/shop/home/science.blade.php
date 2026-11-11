@php($items = array_values($c['items'] ?? []))
<section class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-24 lg:grid-cols-2" x-data="{ active: 0, items: @js($items) }">
    <div class="relative overflow-hidden rounded-2xl bg-obsidian p-6 text-on-dark">
        <svg viewBox="0 0 400 300" class="w-full" aria-hidden="true">
            <defs><linearGradient id="spec" x1="0" x2="1"><stop offset="0" stop-color="var(--bronze)" /><stop offset="1" stop-color="var(--sage)" /></linearGradient></defs>
            <line x1="0" y1="150" x2="170" y2="150" stroke="var(--ivory)" stroke-width="2" />
            <polygon points="170,70 250,230 140,230" fill="var(--obsidian-soft)" stroke="var(--bronze)" stroke-width="1.5"
                     style="transform-origin: 195px 170px; transition: transform .8s" :style="{ transform: `rotate(${items[active]?.angle ?? 0}deg)` }" />
            @foreach (range(0, 5) as $i)
                <line x1="215" y1="160" x2="400" :y2="{{ 120 + $i * 22 }} + Number(items[active]?.angle ?? 0)" stroke="url(#spec)" stroke-width="2" opacity="{{ 1 - $i * 0.12 }}" style="transition: all .8s" />
            @endforeach
        </svg>
        <div class="mt-4 rounded-lg border border-on-dark/10 bg-obsidian-soft p-4">
            <div class="flex justify-between font-mono text-[0.65rem]"><span class="text-bronze">SPECTRAL REFRACTIVE ANALYSIS</span><span x-text="items[active]?.metric"></span></div>
            <p class="mt-2 text-xs text-on-dark-muted">Zero chromatic aberration achieved through proprietary high Abbe-value substrates.</p>
        </div>
    </div>
    <div>
        <x-site.section-head :eyebrow="$c['eyebrow'] ?? ''" :title="$c['title'] ?? ''" :sub="$c['body'] ?? null" />
        <div class="mt-8 space-y-3">
            @foreach ($items as $i => $item)
                <button type="button" @click="active = {{ $i }}" :class="active === {{ $i }} ? 'border-bronze bg-bronze-soft' : 'border-border bg-card hover:border-bronze'"
                        class="flex w-full gap-4 rounded-xl border p-4 text-left transition">
                    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-md bg-primary text-primary-foreground"><x-lucide-sparkles class="h-4 w-4" /></div>
                    <div><div class="text-sm font-semibold">{{ $item['title'] ?? '' }}</div><div class="mt-1 text-xs text-muted-foreground">{{ $item['body'] ?? '' }}</div></div>
                </button>
            @endforeach
        </div>
    </div>
</section>
