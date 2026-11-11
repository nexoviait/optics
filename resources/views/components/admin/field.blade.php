@props(['label', 'name' => null])
<label {{ $attributes->class('block text-xs') }}>
    <span class="field-label">{{ $label }}</span>
    {{ $slot }}
    @if ($name)
        {{-- Shared slot for server errors and inline browser validation (resources/js/app.js). --}}
        <span data-error role="alert" @class(['mt-1 block text-[0.7rem] text-destructive', 'hidden' => ! $errors->has($name)])>{{ $errors->first($name) }}</span>
    @endif
</label>
