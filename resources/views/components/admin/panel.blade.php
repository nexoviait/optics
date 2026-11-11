@props(['title' => null])
<section {{ $attributes->class('panel') }}>
    @if ($title || isset($action))
        <div class="mb-4 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold">{{ $title }}</h2>
            {{ $action ?? '' }}
        </div>
    @endif
    {{ $slot }}
</section>
