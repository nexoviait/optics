@if (! empty($store['favicon_path']))
    <link rel="icon" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($store['favicon_path']) }}">
@else
    <link rel="icon" href="{{ asset('favicon.ico') }}" type="image/x-icon">
@endif
