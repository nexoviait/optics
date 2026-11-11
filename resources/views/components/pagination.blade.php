@if ($paginator->hasPages())
    <nav class="mt-4 flex items-center justify-between gap-3 text-xs" aria-label="Pagination">
        <p class="text-muted-foreground">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>
        <div class="flex gap-1">
            @if ($paginator->onFirstPage())
                <span class="rounded-md border border-border px-3 py-1.5 opacity-50">Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="rounded-md border border-border bg-card px-3 py-1.5">Previous</a>
            @endif
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="rounded-md border border-border bg-card px-3 py-1.5">Next</a>
            @else
                <span class="rounded-md border border-border px-3 py-1.5 opacity-50">Next</span>
            @endif
        </div>
    </nav>
@endif
