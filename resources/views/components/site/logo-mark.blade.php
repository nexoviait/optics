@props(['class' => 'h-8 w-8'])
@if (! empty($store['logo_path']))
    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($store['logo_path']) }}" alt="" {{ $attributes->merge(['class' => $class.' object-contain']) }}>
@else
<svg viewBox="0 0 48 48" {{ $attributes->merge(['class' => $class]) }} aria-hidden="true">
    <path d="M26 6 C14 9 8 20 10 30 C12 38 18 42 22 43 C19 34 21 18 26 6Z" fill="var(--sage)" />
    <path d="M29 9 C38 13 42 24 39 33 C37 39 32 42 29 43 C31 32 31 20 29 9Z" fill="var(--bronze)" />
    <path d="M2 27 L40 20 L40 22 Z" fill="var(--bronze)" opacity="0.9" />
</svg>
@endif
