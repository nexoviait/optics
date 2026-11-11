<x-layouts.app title="AI Fit Advisor — Personal Frame Recommendations" description="Describe your fit and style preferences for AI-guided catalog recommendations and tentative frame sizing.">
    <div class="mx-auto max-w-7xl px-5 py-12 md:py-16">
        <div class="mb-10 flex flex-wrap items-end justify-between gap-6 border-b border-border pb-8">
            <div><p class="eyebrow mb-3 text-muted-foreground">{{ $store['store_name'] ?? 'REFRA' }} · Personal curation</p><h1 class="text-3xl md:text-5xl">The Fit Advisor</h1><p class="mt-4 max-w-xl text-muted-foreground">Your fit. Your silhouette. A considered selection from the atelier.</p></div>
            <a href="{{ route('visionmatch') }}" class="btn-ghost"><x-lucide-scan-face class="h-4 w-4" /> VisionMatch try-on <x-lucide-arrow-right class="h-4 w-4" /></a>
        </div>
        <div class="grid items-start gap-10 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.3fr)]">
            <form method="POST" action="{{ route('fit-advisor.recommend') }}" class="lg:sticky lg:top-24" x-data="{ text: @js(old('preferences', request('preferences', ''))), pending: false }" @submit="pending = true">
                @csrf
                <label for="fit-preferences" class="font-display text-xl">What feels right for you?</label>
                <textarea id="fit-preferences" name="preferences" x-model="text" maxlength="1500" required rows="8" class="input mt-5 min-h-48 bg-card p-4 leading-relaxed"
                          placeholder="I have a narrow face and my current glasses slip. I prefer lightweight titanium, subtle square shapes, and everyday eyeglasses under $600. My current frame width is 130 mm."></textarea>
                <div class="mt-2 flex justify-between gap-3 text-xs text-muted-foreground"><span>Fit · material · style · budget · current frame measurements</span><span class="shrink-0" x-text="text.length + '/1500'"></span></div>
                <p class="mt-4 text-xs leading-relaxed text-muted-foreground">Your description may be sent to our AI service for this recommendation. Avoid personal contact details or prescription information.</p>
                <button type="submit" class="btn mt-6 w-full py-3" :disabled="pending || text.trim().length < 10">
                    <x-lucide-loader-circle x-show="pending" class="h-4 w-4 animate-spin motion-reduce:animate-none" /><x-lucide-sparkles x-show="!pending" class="h-4 w-4" />
                    <span x-text="pending ? 'Considering your fit…' : 'Find my frames'"></span>
                </button>
                @error('preferences')<p role="alert" class="mt-4 text-sm text-destructive">{{ $message }}</p>@enderror
                <div class="mt-8 border-t border-border pt-6"><div class="flex items-center gap-2 text-sm font-medium"><x-lucide-ruler class="h-4 w-4" /> A guide, not a fitting</div><p class="mt-2 text-xs leading-relaxed text-muted-foreground">AI size suggestions and catalog measurements are provisional. Confirm the size guide and final fit with an optician; this is not a biometric scan or prescription assessment.</p></div>
            </form>

            <section aria-label="Frame recommendations" aria-live="polite">
                @if ($result)
                    <p class="eyebrow text-muted-foreground">{{ $result['source'] === 'ai' ? 'AI-curated' : 'Atelier-matched' }} · {{ count($result['recommendations']) }} {{ Str::plural('frame', count($result['recommendations'])) }}</p>
                    <h2 class="mt-3 text-2xl">Your personal selection</h2>
                    <p class="mb-7 mt-4 text-sm leading-relaxed text-muted-foreground">{{ $result['summary'] }}</p>
                    @foreach ($result['recommendations'] as $i => $item)
                        <article class="grid gap-5 border-t border-border py-6 sm:grid-cols-[180px_minmax(0,1fr)]">
                            <a href="{{ route('products.show', $item['product']) }}" class="block"><img src="{{ $item['product']->imageUrl() }}" alt="{{ $item['product']->name }}" class="aspect-square w-full rounded-md object-cover"></a>
                            <div class="min-w-0">
                                <p class="eyebrow text-muted-foreground">Selection 0{{ $i + 1 }} · ${{ number_format((float) $item['product']->price) }}</p>
                                <h3 class="mt-2 text-xl">{{ $item['product']->name }}</h3>
                                <p class="mt-2 text-xs text-muted-foreground">{{ $item['product']->shape }} · {{ $item['product']->material }}</p>
                                <p class="mt-3 text-sm leading-relaxed">{{ $item['reason'] }}</p>
                                <div class="mt-4 border-l-2 border-bronze pl-3">
                                    <p class="text-sm font-medium">Suggested size {{ $item['size']->code }} · {{ $item['size']->width }} mm frame width</p>
                                    <p class="mt-1 text-xs leading-relaxed text-muted-foreground">{{ $item['fit_note'] }}</p>
                                    <p class="mt-1 text-xs text-muted-foreground">Provisional guide: {{ $item['size']->lens_width }}–{{ $item['size']->bridge }}–{{ $item['size']->temple }} mm</p>
                                </div>
                                <a href="{{ route('products.show', $item['product']) }}" class="mt-3 inline-flex items-center gap-1 text-sm underline">View frame & size guide <x-lucide-arrow-right class="h-4 w-4" /></a>
                            </div>
                        </article>
                    @endforeach
                    @if (! count($result['recommendations']))
                        <a href="{{ route('eyeglasses') }}" class="btn-ghost">Browse the catalog <x-lucide-arrow-right class="h-4 w-4" /></a>
                    @endif
                @else
                    <p class="eyebrow text-muted-foreground">The {{ strtok($store['store_name'] ?? 'REFRA', ' ') }} selection</p>
                    <div class="mt-5 grid grid-cols-2 gap-4">
                        @foreach ($featured as $product)
                            <a href="{{ route('products.show', $product) }}"><img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="aspect-[4/3] w-full rounded-md object-cover"><p class="mt-3 text-sm font-medium">{{ $product->name }}</p><p class="mt-1 text-xs text-muted-foreground">{{ $product->shape }}</p></a>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
