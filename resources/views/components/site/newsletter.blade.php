@props(['section' => null])
@php($c = $section?->content ?? [])
<section class="bg-secondary py-20 text-center"
         x-data="{ email: '', busy: false, async submit() { this.busy = true; try { const r = await send('{{ route('newsletter.subscribe') }}', { email: this.email }); toast(r.message); this.email = ''; } catch (e) { toast(e.message, 'error'); } this.busy = false; } }">
    <p class="eyebrow text-muted-foreground">{{ $c['eyebrow'] ?? 'Private circulation' }}</p>
    <h2 class="mt-3 text-3xl font-medium">{{ $c['title'] ?? 'Join The Refraction Dispatch' }}</h2>
    <p class="mx-auto mt-3 max-w-lg text-sm text-muted-foreground">{{ $c['body'] ?? '' }}</p>
    <form class="mx-auto mt-8 flex max-w-md gap-2 px-5" @submit.prevent="submit">
        <label for="newsletter-email" class="sr-only">Email address</label>
        <input id="newsletter-email" type="email" required maxlength="255" x-model="email" placeholder="Enter your email address"
               class="flex-1 rounded-md border border-input bg-card px-4 py-2.5 text-sm outline-none focus:ring-2 focus:ring-ring">
        <button class="rounded-md bg-primary px-6 text-sm text-primary-foreground disabled:opacity-60" :disabled="busy">Subscribe</button>
    </form>
</section>
