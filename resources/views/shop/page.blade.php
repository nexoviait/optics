<x-layouts.app :title="$page->title" :description="$page->meta_description">
    <article class="mx-auto max-w-3xl px-5 py-14">
        <nav class="text-xs text-muted-foreground" aria-label="Breadcrumb"><a href="{{ route('home') }}">Home</a> / {{ $page->title }}</nav>
        <h1 class="mt-4 text-3xl font-semibold md:text-4xl">{{ $page->title }}</h1>
        <p class="mt-2 text-xs text-muted-foreground">Last updated {{ $page->updated_at->format('j F Y') }}</p>
        <div class="cms-content mt-8">{!! $page->html() !!}</div>
    </article>
</x-layouts.app>
