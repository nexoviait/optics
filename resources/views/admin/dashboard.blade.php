@php
    $delta = fn ($v) => $v === null ? 'No prior period' : ($v >= 0 ? '+' : '').$v.'% vs prior 30d';
    $hour = (int) now()->timezone($store['timezone'] ?? config('app.timezone'))->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
@endphp
<x-layouts.admin title="Store Overview">
    <x-admin.page-head eyebrow="Store overview" :title="$greeting.', '.strtok(auth()->user()->name, ' ')" />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.kpi label="Total sales (30d)" :value="'$'.number_format($summary['sales'] / 1000, 1).'k'" :delta="$delta($summary['sales_delta'])" />
        <x-admin.kpi label="Total orders (30d)" :value="$summary['orders']" :delta="$delta($summary['orders_delta'])" />
        <x-admin.kpi label="AOV" :value="'$'.number_format($summary['aov'])" :delta="$delta($summary['aov_delta'])" />
        <x-admin.kpi label="Low in stock" :value="$lowStockCount" delta="SKUs below threshold" :warn="$lowStockCount > 0" />
    </div>

    <div x-data="salesChart(@js($trend->values()))">
    <x-admin.panel title="Commercial throughput (last 30 days)">
        <x-slot:action>
            <div class="flex rounded-full bg-secondary p-1 text-xs">
                @foreach (['sales', 'orders', 'aov'] as $m)
                    <button type="button" @click="metric = '{{ $m }}'" :class="metric === '{{ $m }}' && 'bg-primary text-primary-foreground'" class="rounded-full px-3 py-1 uppercase">{{ $m }}</button>
                @endforeach
            </div>
        </x-slot:action>
        <div class="h-64"><canvas x-ref="canvas" aria-label="Sales trend chart" role="img"></canvas></div>
    </x-admin.panel>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.6fr_1fr]">
        <x-admin.panel :title="'Recent orders ('.$liveOrderCount.' total)'">
            <x-slot:action><a href="{{ route('admin.orders.index') }}" class="text-xs underline">View all</a></x-slot:action>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-xs underline">{{ $order->order_number }}</a></td>
                                <td>{{ $order->customerName() }}@if ($order->isGuest()) <span class="text-[0.6rem] text-muted-foreground">(guest)</span>@endif</td>
                                <td class="text-muted-foreground">{{ $order->created_at->format('d M Y') }}</td>
                                <td>${{ number_format((float) $order->total, 2) }}</td>
                                <td><x-admin.status :s="$order->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted-foreground">No orders yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>
        <x-admin.panel title="Stock critical restock queue">
            <div class="space-y-3">
                @foreach ($stock as $product)
                    <a href="{{ route('admin.products.edit', $product) }}" class="flex items-center gap-3">
                        <img src="{{ $product->imageUrl() }}" alt="" class="h-10 w-12 rounded object-cover">
                        <div class="min-w-0 flex-1"><div class="truncate text-sm">{{ $product->name }}</div><div class="mt-1 h-1.5 rounded bg-secondary"><div @class(['h-full rounded', 'bg-destructive' => $product->isLowStock(), 'bg-sage' => ! $product->isLowStock()]) style="width: {{ min(100, $product->stock * 2.5) }}%"></div></div></div>
                        <span class="font-mono text-xs">{{ $product->stock }}</span>
                    </a>
                @endforeach
            </div>
        </x-admin.panel>
    </div>
</x-layouts.admin>
