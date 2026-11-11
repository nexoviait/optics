@props(['dark' => false])
@php($name = strtoupper(strtok($store['store_name'] ?? 'REFRA', ' ')))
<a href="{{ route('home') }}" class="flex items-center gap-2.5">
    <x-site.logo-mark />
    <div class="leading-none">
        <div @class(['font-display text-lg font-semibold tracking-[0.3em]', 'text-on-dark' => $dark, 'text-foreground' => ! $dark])>{{ $name }}</div>
        <div @class(['mt-1 text-[0.5rem] uppercase tracking-[0.28em]', 'text-on-dark-muted' => $dark, 'text-muted-foreground' => ! $dark])>{{ $store['store_tagline'] ?? '' }}</div>
    </div>
</a>
