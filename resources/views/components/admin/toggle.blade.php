@props(['name', 'checked' => false, 'label' => null, 'sub' => null])
<label class="flex items-center justify-between gap-4 rounded-lg border border-border p-3 text-sm">
    <span>{{ $label }}@if ($sub)<span class="block text-xs text-muted-foreground">{{ $sub }}</span>@endif</span>
    <span class="switch">
        <input type="hidden" name="{{ $name }}" value="0">
        <input type="checkbox" name="{{ $name }}" value="1" @checked($checked) {{ $attributes }}>
    </span>
</label>
