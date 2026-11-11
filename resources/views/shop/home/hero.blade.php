@php($heroImage = $c['image'] ?? 'images/hero.jpg')
<section class="relative overflow-hidden bg-obsidian text-on-dark">
    <div class="mx-auto grid max-w-7xl gap-12 px-5 py-20 lg:grid-cols-[1.1fr_1fr] lg:py-28">
        <div>
            <x-site.badge dark>{{ $c['badge'] ?? '' }}</x-site.badge>
            <h1 class="mt-6 text-4xl font-semibold uppercase leading-[1.05] md:text-6xl">{!! nl2br(e(str_replace('. ', ".\n", $c['headline'] ?? ''))) !!}</h1>
            <p class="mt-6 max-w-lg text-on-dark-muted">{{ $c['subheadline'] ?? '' }}</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('eyeglasses') }}" class="flex items-center gap-2 rounded-md bg-ivory px-5 py-3 text-sm font-medium text-obsidian">{{ $c['primary_cta'] ?? 'Explore' }} <x-lucide-arrow-up-right class="h-4 w-4" /></a>
                <a href="{{ route('visionmatch') }}" class="flex items-center gap-2 rounded-md border border-on-dark/20 px-5 py-3 text-sm"><x-lucide-scan-face class="h-4 w-4" /> {{ $c['secondary_cta'] ?? 'Virtual Try-On' }}</a>
            </div>
            @if (! empty($c['stats']))
                <div class="mt-14 grid max-w-lg grid-cols-3 gap-6 border-t border-on-dark/10 pt-6">
                    @foreach ($c['stats'] as $stat)
                        <div><div class="font-display text-2xl font-semibold">{{ $stat['value'] ?? '' }}</div><div class="mt-1 text-xs text-on-dark-muted">{{ $stat['label'] ?? '' }}</div></div>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="relative overflow-hidden rounded-2xl border border-on-dark/10">
            <img src="{{ str_starts_with($heroImage, 'images/') ? asset($heroImage) : asset('storage/'.$heroImage) }}" alt="REFRA frames refracting a beam of light" width="1600" height="1008" class="h-full min-h-[340px] w-full object-cover">
            <span class="eyebrow absolute left-4 top-4 rounded-full bg-obsidian/70 px-3 py-1 text-[0.6rem] text-bronze backdrop-blur">● Spectrometer Active</span>
            <div class="absolute inset-x-0 bottom-0 flex justify-between bg-obsidian/80 px-4 py-3 font-mono text-[0.65rem] backdrop-blur">
                <span>{{ $c['caption_model'] ?? '' }}</span><span class="text-bronze">{{ $c['caption_metric'] ?? '' }}</span>
            </div>
            <div class="prism-beam pointer-events-none absolute left-0 top-1/3 h-px w-full opacity-60"></div>
        </div>
    </div>
</section>
