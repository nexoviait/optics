@php
    $content = $section->content ?? [];
    $fields = collect($content)->reject(fn ($v) => is_array($v));
    $lists = collect($content)->filter(fn ($v) => is_array($v));
@endphp
<x-layouts.admin :title="'Edit '.$section->label">
    <form method="POST" action="{{ route('admin.content.update', $section) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf @method('PUT')
        <x-admin.page-head eyebrow="CMS · {{ $section->key }}" :title="$section->label">
            <a href="{{ route('admin.content.index') }}" class="btn-ghost btn-sm">Back</a>
            <button class="btn btn-sm">Save section</button>
        </x-admin.page-head>
        <x-admin.panel title="Section">
            <x-admin.field label="Admin label" name="label"><input name="label" value="{{ old('label', $section->label) }}" required class="input"></x-admin.field>
        </x-admin.panel>
        @if ($fields->isNotEmpty())
            <x-admin.panel title="Copy">
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach ($fields as $key => $value)
                        @continue($key === 'image')
                        <x-admin.field :label="Str::headline($key)" :name="'fields.'.$key" :class="strlen((string) $value) > 80 ? 'md:col-span-2' : ''">
                            @if (strlen((string) $value) > 80)
                                <textarea name="fields[{{ $key }}]" rows="3" class="input">{{ old('fields.'.$key, $value) }}</textarea>
                            @else
                                <input name="fields[{{ $key }}]" value="{{ old('fields.'.$key, $value) }}" class="input">
                            @endif
                        </x-admin.field>
                    @endforeach
                </div>
            </x-admin.panel>
        @endif
        @if (array_key_exists('image', $content))
            <x-admin.panel title="Image">
                <div class="flex flex-wrap items-center gap-4">
                    <img src="{{ str_starts_with((string) $content['image'], 'images/') ? asset($content['image']) : asset('storage/'.$content['image']) }}" alt="" class="h-24 w-36 rounded-lg object-cover">
                    <input type="file" name="image" accept="image/*" aria-label="Replace image" class="text-sm">
                </div>
            </x-admin.panel>
        @endif
        @foreach ($lists as $key => $items)
            <x-admin.panel :title="Str::headline($key).' ('.count($items).' items)'">
                <textarea name="lists[{{ $key }}]" aria-label="{{ Str::headline($key) }} (JSON list)" rows="{{ min(24, 4 + count($items) * 4) }}" class="input font-mono text-xs" spellcheck="false">{{ old('lists.'.$key, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}</textarea>
                @error('lists.'.$key)<p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
                <p class="mt-2 text-xs text-muted-foreground">JSON list — keep the same keys for each item. Add or remove items to change what the homepage shows.</p>
            </x-admin.panel>
        @endforeach
    </form>
</x-layouts.admin>
