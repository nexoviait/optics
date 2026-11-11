<x-layouts.admin title="Orders">
    <x-admin.page-head eyebrow="Fulfillment" title="Orders">
        <a href="{{ route('admin.analytics.export', 'orders') }}" class="btn-ghost btn-sm"><x-lucide-download class="h-3.5 w-3.5" />Export CSV</a>
        <a href="{{ route('admin.orders.create') }}" class="btn btn-sm"><x-lucide-plus class="h-3.5 w-3.5" />Create Manual Order</a>
    </x-admin.page-head>
    <div class="grid grid-cols-2 gap-3 md:grid-cols-6">
        @foreach (config('store.order_statuses') as $s)
            <a href="{{ route('admin.orders.index', ['status' => $s]) }}" @class(['rounded-xl border bg-card p-4', 'border-primary' => $status === $s, 'border-border' => $status !== $s])>
                <div class="eyebrow text-[0.55rem] text-muted-foreground">{{ $s }}</div>
                <div class="mt-1 font-display text-xl font-semibold">{{ $counts[$s] ?? 0 }}</div>
            </a>
        @endforeach
    </div>
    <x-admin.panel :title="($status ?? 'All').' orders'">
        <x-slot:action>
            <div class="flex items-center gap-3">
                <form method="GET" class="relative w-56">
                    @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                    <x-lucide-search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" />
                    <input name="q" value="{{ $search }}" placeholder="Order or client" aria-label="Search orders" class="input pl-9">
                </form>
                @if ($status || $search)<a href="{{ route('admin.orders.index') }}" class="text-xs underline">Show all</a>@endif
            </div>
        </x-slot:action>
        <div class="table-scroll">
            <table class="data-table">
                <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Channel</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td><a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-xs underline">{{ $order->order_number }}</a></td>
                            <td>{{ $order->customerName() }}@if ($order->isGuest()) <span class="text-[0.6rem] text-muted-foreground">(guest)</span>@endif</td>
                            <td class="max-w-xs truncate text-xs">{{ $order->items->pluck('name')->implode(', ') }}</td>
                            <td class="text-xs capitalize">{{ $order->channel }}</td>
                            <td class="text-muted-foreground">{{ $order->created_at->format('d M Y') }}</td>
                            <td>${{ number_format((float) $order->total, 2) }}</td>
                            <td><x-admin.status :s="$order->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted-foreground">No orders yet. They appear here as soon as a client checks out.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $orders->links() }}
    </x-admin.panel>
</x-layouts.admin>
