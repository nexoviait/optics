<section class="mx-auto max-w-3xl px-5 py-24 text-center">
    <x-site.badge>{{ $c['badge'] ?? '' }}</x-site.badge>
    <h2 class="mt-5 text-3xl font-medium md:text-4xl">{{ $c['title'] ?? '' }}</h2>
    <p class="mt-3 font-medium">{{ $c['lead'] ?? '' }}</p>
    <p class="mx-auto mt-2 max-w-lg text-sm text-muted-foreground">{{ $c['body'] ?? '' }}</p>
    <div class="mx-auto mt-10 max-w-md rounded-2xl border border-border bg-card p-10">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-full border border-border"><x-lucide-scan-line class="h-6 w-6" /></div>
        <a href="{{ route('visionmatch') }}" class="mt-6 inline-flex items-center gap-2 rounded-md bg-primary px-5 py-2.5 text-sm text-primary-foreground"><x-lucide-camera class="h-4 w-4" /> Scan Face with Camera</a>
        <p class="eyebrow mt-4 text-[0.55rem] text-muted-foreground">Takes 5 seconds • Processed in your browser</p>
        <a href="{{ route('visionmatch') }}" class="mt-4 inline-flex items-center gap-1 text-xs text-muted-foreground underline"><x-lucide-upload class="h-3 w-3" /> or upload portrait photo</a>
    </div>
</section>
