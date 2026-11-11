@props(['eyebrow', 'title', 'sub' => null, 'center' => false, 'level' => 2])
@php($tag = (int) $level === 1 ? 'h1' : 'h2')
<div @class(['text-center' => $center])>
    <p class="eyebrow text-muted-foreground">{{ $eyebrow }}</p>
    <{{ $tag }} class="mt-3 text-3xl font-medium md:text-4xl">{{ $title }}</{{ $tag }}>
    @if ($sub)
        <p @class(['mt-3 max-w-2xl text-sm text-muted-foreground', 'mx-auto' => $center])>{{ $sub }}</p>
    @endif
</div>
