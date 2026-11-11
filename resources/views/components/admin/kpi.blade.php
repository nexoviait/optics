@props(['label', 'value', 'delta' => null, 'warn' => false])
<div class="rounded-2xl border border-border bg-card p-5">
    <p class="eyebrow text-[0.55rem] text-muted-foreground">{{ $label }}</p>
    <p class="mt-2 font-display text-2xl font-semibold">{{ $value }}</p>
    @if ($delta !== null)
        <p @class(['mt-1 text-xs', 'text-destructive' => $warn, 'text-muted-foreground' => ! $warn])>{{ $delta }}</p>
    @endif
</div>
