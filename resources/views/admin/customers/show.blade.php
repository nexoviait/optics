@php($active = $prescriptions->firstWhere('source', 'manual'))
<x-layouts.admin :title="$customer->name">
    <x-admin.page-head :eyebrow="$customer->tier" :title="$customer->name">
        <a href="{{ route('admin.customers.index') }}" class="btn-ghost btn-sm">Back</a>
        <a href="{{ route('admin.orders.create') }}" class="btn btn-sm">New order</a>
    </x-admin.page-head>
    <div class="grid gap-4 sm:grid-cols-4">
        <x-admin.kpi label="Lifetime value" :value="'$'.number_format($ltv, 2)" />
        <x-admin.kpi label="Orders" :value="$orders->count()" />
        <x-admin.kpi label="Email" :value="$customer->email" />
        <x-admin.kpi label="Member since" :value="$customer->created_at->format('M Y')" />
    </div>

    <x-admin.panel title="Client profile">
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}" class="grid gap-4 sm:grid-cols-4">
            @csrf @method('PATCH')
            <x-admin.field label="Circle tier" name="tier"><select name="tier" class="input">@foreach (config('store.tiers') as $t)<option @selected($customer->tier === $t)>{{ $t }}</option>@endforeach</select></x-admin.field>
            <x-admin.field label="Phone" name="phone"><input name="phone" value="{{ old('phone', $customer->phone) }}" class="input"></x-admin.field>
            <x-admin.field label="City" name="city"><input name="city" value="{{ old('city', $customer->city) }}" class="input"></x-admin.field>
            <div class="flex items-end"><button class="btn w-full">Save</button></div>
        </form>
    </x-admin.panel>

    <x-admin.panel title="Active optical calibration & Rx matrix">
        @if ($active)
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Eye</th><th>SPH</th><th>CYL</th><th>Axis</th><th>Add</th><th>PD</th></tr></thead>
                    <tbody>
                        <tr><td class="font-semibold">OD (Right)</td><td class="font-mono">{{ $active->r_sph ?? '—' }}</td><td class="font-mono">{{ $active->r_cyl ?? '—' }}</td><td class="font-mono">{{ $active->r_axis ?? '—' }}</td><td class="font-mono">{{ $active->add_power ?? '—' }}</td><td rowspan="2" class="font-mono">{{ $active->pd ? $active->pd.' mm' : '—' }}</td></tr>
                        <tr><td class="font-semibold">OS (Left)</td><td class="font-mono">{{ $active->l_sph ?? '—' }}</td><td class="font-mono">{{ $active->l_cyl ?? '—' }}</td><td class="font-mono">{{ $active->l_axis ?? '—' }}</td><td class="font-mono">{{ $active->add_power ?? '—' }}</td></tr>
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-muted-foreground">No manually entered prescription on file.</p>
        @endif
        @foreach ($prescriptions->where('source', 'upload') as $rx)
            <p class="mt-2 text-xs">Uploaded prescription: <a href="{{ route('admin.prescriptions.file', $rx) }}" target="_blank" rel="noopener" class="underline">{{ $rx->file_name }}</a> · {{ $rx->created_at->format('d M Y') }}</p>
        @endforeach
    </x-admin.panel>

    <x-admin.panel title="Atelier order history">
        @if ($orders->isNotEmpty())
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Order</th><th>Items</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($orders as $order)
                            <tr>
                                <td><a href="{{ route('admin.orders.show', $order) }}" class="font-mono text-xs underline">{{ $order->order_number }}</a></td>
                                <td class="text-xs">{{ $order->items->pluck('name')->implode(', ') }}</td>
                                <td>{{ $order->created_at->format('d M Y') }}</td>
                                <td>${{ number_format((float) $order->total, 2) }}</td>
                                <td><x-admin.status :s="$order->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-muted-foreground">No orders yet.</p>
        @endif
    </x-admin.panel>
</x-layouts.admin>
