@php
    $tints = [
        ['label' => 'Obsidian Polarized', 'filter' => 'saturate(1.15) contrast(1.15) brightness(0.72) sepia(0.15)', 'trans' => '15%', 'glare' => '99.4%'],
        ['label' => 'Champagne Gold Gradient', 'filter' => 'sepia(0.55) saturate(1.3) brightness(0.8) contrast(1.1)', 'trans' => '22%', 'glare' => '96.8%'],
        ['label' => 'Deep Sage Mineral', 'filter' => 'hue-rotate(-20deg) saturate(0.9) brightness(0.7) contrast(1.2)', 'trans' => '12%', 'glare' => '99.1%'],
        ['label' => 'Gradient Rose Lens', 'filter' => 'hue-rotate(-30deg) sepia(0.3) brightness(0.82) contrast(1.05)', 'trans' => '28%', 'glare' => '94.2%'],
    ];
    $hero = $products->first();
@endphp
<x-layouts.app title="Sunglasses — Architectural Sun & UV400 Prism Protection" description="Handcrafted β-titanium and bio-acetate sunglasses with polarized mineral prism lenses and UV400 protection.">
    <section class="mx-auto grid max-w-7xl items-center gap-12 px-5 py-16 lg:grid-cols-2">
        <div>
            <x-site.badge>Cat 3 UV400 • Mineral prism series</x-site.badge>
            <h1 class="mt-6 text-4xl font-medium leading-tight md:text-5xl">Architectural Sun &amp; Spectral Light. <span class="font-serif font-normal italic">UV400 Prism Protection.</span></h1>
            <p class="mt-5 max-w-lg text-muted-foreground">Handcrafted Japanese Beta-Titanium and Italian bio-acetate frames fitted with medical-grade polarized mineral glass and prismatic gradient filters.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="#collection" class="btn px-5 py-3">Explore Sun Collection <x-lucide-arrow-right class="h-4 w-4" /></a>
                <a href="{{ route('visionmatch') }}" class="btn-ghost px-5 py-3"><x-lucide-scan-face class="h-4 w-4" /> Virtual Try-On</a>
            </div>
        </div>
        <div class="relative overflow-hidden rounded-2xl bg-secondary">
            <img src="{{ asset('images/sun-portrait.jpg') }}" alt="Model wearing REFRA sunglasses" width="1024" height="1280" fetchpriority="high" decoding="async" class="aspect-[4/5] w-full object-cover">
            @if ($hero)
                <a href="{{ route('products.show', $hero) }}" class="absolute inset-x-4 bottom-4 flex items-center justify-between rounded-xl bg-card/95 p-4 backdrop-blur">
                    <div><div class="eyebrow text-[0.55rem] text-muted-foreground">Cat 3 polarized precision</div><div class="text-sm font-semibold">{{ $hero->name }}</div></div>
                    <div class="font-display text-lg font-semibold">${{ number_format((float) $hero->price) }}</div>
                </a>
            @endif
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-5 py-16"
             x-data="{ pos: 50, tint: 0, tints: @js($tints), dragging: false,
                seek(e) { const r = this.$refs.stage.getBoundingClientRect(); this.pos = Math.min(100, Math.max(0, ((e.clientX - r.left) / r.width) * 100)); },
                start(e) { if (e.button) return; this.dragging = true; e.currentTarget.setPointerCapture(e.pointerId); this.seek(e); },
                nudge(by) { this.pos = Math.min(100, Math.max(0, this.pos + by)); } }">
        <x-site.section-head center eyebrow="Perfection calibrator" title="Interactive Tint & Polarization Optics" sub="Compare ambient unpolarized glare against REFRA mineral prism filters. Drag the divider to experience instant chromatic calibration." />
        <div class="mt-8 flex flex-wrap justify-center gap-2" role="group" aria-label="Lens tint">
            @foreach ($tints as $i => $t)
                <button type="button" @click="tint = {{ $i }}" :aria-pressed="tint === {{ $i }}" :class="tint === {{ $i }} ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:border-foreground/40'" class="eyebrow rounded-full border px-4 py-2 text-[0.6rem] transition-colors">{{ $t['label'] }}</button>
            @endforeach
        </div>
        <div x-ref="stage" @pointerdown="start($event)" @pointermove="dragging && seek($event)" @pointerup="dragging = false" @pointercancel="dragging = false" @dragstart.prevent
             class="relative mt-8 cursor-ew-resize touch-pan-y select-none overflow-hidden rounded-2xl border border-border bg-secondary">
            <img src="{{ asset('images/coast.jpg') }}" alt="Unfiltered coastal glare" width="1600" height="912" loading="lazy" decoding="async" draggable="false" class="aspect-[4/3] w-full object-cover sm:aspect-[16/8]">
            <div class="absolute inset-0 will-change-[clip-path]" :style="{ clipPath: `inset(0 ${100 - pos}% 0 0)` }">
                <img src="{{ asset('images/coast.jpg') }}" alt="" aria-hidden="true" loading="lazy" decoding="async" draggable="false" class="h-full w-full object-cover transition-[filter] duration-500" :style="{ filter: tints[tint].filter }">
            </div>
            <span class="eyebrow pointer-events-none absolute left-4 top-4 rounded bg-obsidian/80 px-2 py-1 text-[0.55rem] text-on-dark">● REFRA <span x-text="tints[tint].label"></span></span>
            <span class="eyebrow pointer-events-none absolute right-4 top-4 hidden rounded bg-card/80 px-2 py-1 text-[0.55rem] sm:block">Ambient unpolarized glare</span>
            <div class="absolute inset-y-0 w-0.5 -translate-x-1/2 bg-ivory" :style="{ left: pos + '%' }">
                <div role="slider" tabindex="0" aria-label="Compare filtered and unfiltered view" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="Math.round(pos)" :aria-valuetext="Math.round(pos) + '% filtered'"
                     @keydown.arrow-left.prevent="nudge(-2)" @keydown.arrow-right.prevent="nudge(2)" @keydown.home.prevent="pos = 0" @keydown.end.prevent="pos = 100"
                     :class="dragging && 'scale-110'"
                     class="absolute left-1/2 top-1/2 grid h-10 w-10 -translate-x-1/2 -translate-y-1/2 place-items-center rounded-full border-2 border-ivory bg-obsidian/70 text-on-dark shadow-lg backdrop-blur transition-transform focus:outline-none focus-visible:ring-2 focus-visible:ring-ivory focus-visible:ring-offset-2 focus-visible:ring-offset-obsidian">
                    <x-lucide-chevrons-left-right class="h-4 w-4" />
                </div>
            </div>
            <div @pointerdown.stop class="absolute inset-x-4 bottom-4 grid cursor-default grid-cols-3 gap-4 rounded-xl bg-card/90 p-4 backdrop-blur">
                <div><div class="eyebrow text-[0.5rem] text-muted-foreground">Glare reduction</div><div class="font-mono text-sm font-semibold" x-text="tints[tint].glare"></div></div>
                <div><div class="eyebrow text-[0.5rem] text-muted-foreground">Light transmission</div><div class="font-mono text-sm font-semibold" x-text="tints[tint].trans"></div></div>
                <div><div class="eyebrow text-[0.5rem] text-muted-foreground">Base curve</div><div class="font-mono text-sm font-semibold">Base 6 Toric Prism</div></div>
            </div>
        </div>
    </section>

    <section id="collection" class="mx-auto max-w-7xl px-5 py-16">
        <x-site.section-head eyebrow="Architectural sunline collection" title="Sculpted Sunglasses Series" sub="Precision ground mineral crystal. Zero-gravity Japanese Beta-Titanium. Certified Cat 3 UV400." />
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @forelse ($products as $product)
                <x-site.product-card :product="$product" />
            @empty
                <p class="col-span-full text-sm text-muted-foreground">The sun collection is being restocked.</p>
            @endforelse
        </div>
        @if ($hero)
            <div class="mt-8 flex flex-wrap items-center gap-4 rounded-xl border border-border bg-card p-5">
                <div class="grid h-10 w-10 place-items-center rounded-md bg-primary text-primary-foreground"><x-lucide-upload class="h-4 w-4" /></div>
                <div class="flex-1"><div class="text-sm font-semibold">Need Bespoke Diopter Surfacing or High-Astigmatism Sun Optics?</div><div class="text-xs text-muted-foreground">Upload your prescription and our master opticians will surface polarized Rx lenses to match.</div></div>
                <a href="{{ route('products.show', $hero) }}" class="btn btn-sm">Upload Prescription</a>
            </div>
        @endif
    </section>

    <section class="bg-secondary py-20">
        <div class="mx-auto max-w-7xl px-5">
            <x-site.section-head eyebrow="Refractive physics & optical protection" title="The Science of Sun Refraction" sub="Every REFRA sun lens is a calibrated scientific instrument engineered to eliminate radiant ocular fatigue and sharpen contrast thresholds." />
            <div class="mt-10 grid gap-5 md:grid-cols-3">
                @foreach ([['01', 'aperture', 'Zero Optical Distortion Curve', 'Mineral glass ground on a toric base-6 curve keeps peripheral vision perfectly true, with no wavefront warping.'], ['02', 'droplets', 'Vacuum-Deposition Oleophobic Shield', 'A hydrophobic, oleophobic outer layer repels salt spray, sunscreen and fingerprints for lasting clarity.'], ['03', 'glasses', 'Prescription Sun Surfacing', 'Any REFRA sun frame can be surfaced to your exact Rx, including progressive and high-cylinder corrections.']] as [$n, $icon, $t, $d])
                    <div class="rounded-2xl border border-border bg-card p-6">
                        <div class="flex justify-between"><div class="grid h-9 w-9 place-items-center rounded-md bg-sage-soft">@svg('lucide-'.$icon, 'h-4 w-4')</div><span class="font-display text-3xl text-muted-foreground/40">{{ $n }}</span></div>
                        <h3 class="mt-6 font-semibold">{{ $t }}</h3><p class="mt-2 text-sm text-muted-foreground">{{ $d }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <section id="home-trial" class="mx-auto max-w-7xl px-5 py-20"
             x-data="{ picked: [], name: '', email: '', busy: false,
                toggle(id) {
                    if (this.picked.includes(id)) return (this.picked = this.picked.filter(i => i !== id));
                    if (this.picked.length >= 4) return toast('Your box holds up to 4 frames. Remove one to swap it.', 'error');
                    this.picked.push(id);
                },
                async book() {
                    if (!this.picked.length) return toast('Select at least one frame for your box.', 'error');
                    this.busy = true;
                    try {
                        const r = await send('{{ route('requests.home-trial') }}', { product_ids: this.picked, name: this.name, email: this.email });
                        toast(r.message);
                        this.picked = [];
                        this.name = '';
                        this.email = '';
                    } catch (e) {
                        toast(e.message, 'error');
                    } finally {
                        this.busy = false;
                    }
                } }">
        <form @submit.prevent="book" class="grid gap-10 rounded-3xl bg-obsidian p-8 text-on-dark md:p-12 lg:grid-cols-[1.2fr_1fr]">
            <div>
                <x-site.badge dark>Complimentary home atelier experience</x-site.badge>
                <h2 class="mt-6 text-3xl font-medium">Select up to 4 Sun Frames for a 7-Day Atelier Home Trial ($0 deposit)</h2>
                <p class="mt-4 text-sm text-on-dark-muted">Experience the weight, balance and optical clarity under your own sunlight. We deliver a custom presentation box curated to your facial architecture with prepaid return logistics.</p>
                @guest
                    <div class="mt-6 grid gap-3 sm:grid-cols-2">
                        <label class="block">
                            <span class="field-label sr-only">Your name</span>
                            <input name="name" x-model="name" required maxlength="80" autocomplete="name" placeholder="Your name" class="w-full rounded-md border border-on-dark/20 bg-obsidian-soft px-3 py-2 text-sm text-on-dark placeholder:text-on-dark-muted focus:border-bronze focus:outline-none">
                        </label>
                        <label class="block">
                            <span class="field-label sr-only">Email</span>
                            <input name="email" type="email" x-model="email" required maxlength="255" autocomplete="email" placeholder="Email" class="w-full rounded-md border border-on-dark/20 bg-obsidian-soft px-3 py-2 text-sm text-on-dark placeholder:text-on-dark-muted focus:border-bronze focus:outline-none">
                        </label>
                    </div>
                @endguest
                <div class="mt-8 flex flex-wrap gap-3">
                    <button type="submit" :disabled="busy || !{{ $products->count() }}" class="inline-flex items-center gap-2 rounded-md bg-bronze px-5 py-3 text-sm font-medium text-obsidian transition hover:brightness-110 disabled:cursor-not-allowed disabled:opacity-60">
                        <x-lucide-loader-circle x-show="busy" x-cloak class="h-4 w-4 animate-spin" />
                        <span x-text="busy ? 'Booking…' : 'Book Home Trial Box'">Book Home Trial Box</span>
                    </button>
                    <a href="{{ route('visionmatch') }}" class="rounded-md border border-on-dark/20 px-5 py-3 text-sm transition hover:border-on-dark/50">Get AI frame suggestions</a>
                </div>
            </div>
            <div class="rounded-2xl border border-on-dark/10 bg-obsidian-soft p-5">
                <div class="flex items-center justify-between"><span class="eyebrow text-[0.6rem] text-bronze"><x-lucide-package class="mr-1 inline h-3 w-3" />Your trial box</span><span class="font-mono text-xs" x-text="picked.length + '/4'" aria-live="polite">0/4</span></div>
                <div class="mt-4 max-h-80 space-y-2 overflow-y-auto pr-1">
                    @forelse ($products as $product)
                        <button type="button" @click="toggle({{ $product->id }})" :aria-pressed="picked.includes({{ $product->id }})" :class="picked.includes({{ $product->id }}) ? 'border-bronze bg-bronze/10' : 'border-on-dark/10 hover:border-on-dark/30'" class="flex w-full items-center gap-3 rounded-lg border px-3 py-2 text-left text-sm transition-colors">
                            <span :class="picked.includes({{ $product->id }}) ? 'border-bronze bg-bronze text-obsidian' : 'border-on-dark/30'" class="grid h-4 w-4 shrink-0 place-items-center rounded border"><x-lucide-check x-show="picked.includes({{ $product->id }})" x-cloak class="h-3 w-3" /></span>
                            <span class="flex-1">{{ $product->name }}</span>
                            <span class="font-mono text-xs text-on-dark-muted">${{ number_format((float) $product->price) }}</span>
                        </button>
                    @empty
                        <p class="text-sm text-on-dark-muted">No sun frames are available for home trial right now.</p>
                    @endforelse
                </div>
            </div>
        </form>
    </section>
    <x-site.newsletter :section="$newsletter" />
</x-layouts.app>
