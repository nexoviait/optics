@php($editing = $page->exists)
<x-layouts.admin :title="$editing ? $page->title : 'New page'">
    <form method="POST" action="{{ $editing ? route('admin.pages.update', $page) : route('admin.pages.store') }}" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-admin.page-head eyebrow="CMS · Pages" :title="$editing ? $page->title : 'New page'">
            <a href="{{ route('admin.pages.index') }}" class="btn-ghost btn-sm">Back</a>
            @if ($editing && $page->is_published)<a href="{{ route('pages.show', $page) }}" target="_blank" class="btn-ghost btn-sm">View ↗</a>@endif
            <button class="btn btn-sm">Save page</button>
        </x-admin.page-head>
        <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
            <x-admin.panel title="Content">
                <div class="space-y-4">
                    <x-admin.field label="Title" name="title"><input name="title" value="{{ old('title', $page->title) }}" required class="input"></x-admin.field>
                    <x-admin.field label="Body (Markdown: ## headings, **bold**, - lists, [links](/url), tables)" name="body">
                        <textarea name="body" rows="22" required class="input font-mono text-xs" spellcheck="true">{{ old('body', $page->body) }}</textarea>
                    </x-admin.field>
                </div>
            </x-admin.panel>
            <x-admin.panel title="Publishing" class="h-fit">
                <div class="space-y-4">
                    <x-admin.field label="URL slug (auto from title if empty)" name="slug"><input name="slug" value="{{ old('slug', $page->slug) }}" class="input font-mono"></x-admin.field>
                    <x-admin.field label="Footer column" name="footer_group">
                        <select name="footer_group" class="input"><option value="">Not in footer</option>@foreach (config('store.footer_groups') as $key => $label)<option value="{{ $key }}" @selected(old('footer_group', $page->footer_group) === $key)>{{ $label }}</option>@endforeach</select>
                    </x-admin.field>
                    <x-admin.field label="Sort order" name="sort_order"><input type="number" name="sort_order" value="{{ old('sort_order', $page->sort_order ?? 0) }}" class="input"></x-admin.field>
                    <x-admin.field label="SEO description" name="meta_description"><textarea name="meta_description" rows="3" maxlength="255" class="input">{{ old('meta_description', $page->meta_description) }}</textarea></x-admin.field>
                    <x-admin.toggle name="is_published" :checked="(bool) old('is_published', $page->is_published)" label="Published" sub="Unpublished pages return 404 on the storefront" />
                </div>
            </x-admin.panel>
        </div>
    </form>
    @if ($editing)
        <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" onsubmit="return confirm('Delete this page?')" class="flex justify-end">@csrf @method('DELETE')<button class="text-xs text-destructive underline">Delete page</button></form>
    @endif
</x-layouts.admin>
