<x-layouts.admin title="Products & Inventory">
    <x-admin.page-head eyebrow="Catalog" title="Products & Inventory">
        <a href="{{ route('admin.lenses.index') }}" class="btn-ghost btn-sm">Optical Lenses</a>
        <a href="{{ route('admin.products.create') }}" class="btn btn-sm"><x-lucide-plus class="h-3.5 w-3.5" />Add New Product</a>
    </x-admin.page-head>
    <x-admin.panel>
        <div class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex rounded-full bg-secondary p-1 text-xs">
                @foreach (['' => 'All'] + config('store.categories') as $key => $label)
                    <a href="{{ route('admin.products.index', array_filter(['category' => $key ?: null, 'q' => $search ?: null])) }}" @class(['rounded-full px-3 py-1', 'bg-primary text-primary-foreground' => ($category ?? '') === $key])>{{ $label }}</a>
                @endforeach
            </div>
            <form method="GET" class="relative ml-auto w-64">
                @if ($category)<input type="hidden" name="category" value="{{ $category }}">@endif
                <x-lucide-search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                <input name="q" value="{{ $search }}" placeholder="Search name or SKU" aria-label="Search products" class="input pl-9">
            </form>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Product</th><th>SKU</th><th>Type</th><th>Price</th><th>Margin</th><th>Stock</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($products as $product)
                        <tr class="hover:bg-secondary">
                            <td><a href="{{ route('admin.products.edit', $product) }}" class="flex items-center gap-3"><img src="{{ $product->imageUrl() }}" alt="" class="h-10 w-12 rounded object-cover"><span class="font-medium">{{ $product->name }}</span></a></td>
                            <td class="font-mono text-xs">{{ $product->sku }}</td>
                            <td>{{ config('store.categories.'.$product->category) }}</td>
                            <td>${{ number_format((float) $product->price) }}</td>
                            <td>{{ $product->marginPercent() }}%</td>
                            <td><span @class(['font-mono', 'text-destructive' => $product->isLowStock()])>{{ $product->stock }}</span></td>
                            <td><x-admin.status :s="$product->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted-foreground">No products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $products->links() }}
    </x-admin.panel>
</x-layouts.admin>
