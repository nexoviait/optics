<x-layouts.admin title="Content Pages">
    <x-admin.page-head eyebrow="CMS" title="Content Pages">
        <a href="{{ route('admin.pages.create') }}" class="btn btn-sm"><x-lucide-plus class="h-3.5 w-3.5" />New page</a>
    </x-admin.page-head>
    <x-admin.panel title="FAQ, guides & legal pages">
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Title</th><th>URL</th><th>Footer column</th><th>Updated</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @forelse ($pages as $page)
                        <tr>
                            <td class="font-medium">{{ $page->title }}</td>
                            <td><a href="{{ route('pages.show', $page) }}" target="_blank" class="font-mono text-xs underline">/pages/{{ $page->slug }}</a></td>
                            <td class="text-xs">{{ config('store.footer_groups.'.$page->footer_group, '—') }}</td>
                            <td class="text-muted-foreground">{{ $page->updated_at->format('d M Y') }}</td>
                            <td><x-admin.status :s="$page->is_published ? 'Live' : 'Draft'" /></td>
                            <td><a href="{{ route('admin.pages.edit', $page) }}" class="text-xs underline">Edit</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted-foreground">No pages yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.panel>
</x-layouts.admin>
