@props(['dark' => false])
<span {{ $attributes->class([
    'eyebrow inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[0.6rem]',
    'border-on-dark/15 text-on-dark-muted' => $dark,
    'border-border bg-card text-muted-foreground' => ! $dark,
]) }}>
    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-bronze"></span>{{ $slot }}
</span>
