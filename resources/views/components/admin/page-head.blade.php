@props(['eyebrow' => null, 'title'])
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        @if ($eyebrow)<p class="eyebrow text-[0.6rem] text-muted-foreground">{{ $eyebrow }}</p>@endif
        <h1 class="mt-1 text-2xl font-semibold">{{ $title }}</h1>
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</div>
