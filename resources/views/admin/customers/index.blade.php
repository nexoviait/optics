<x-layouts.admin title="Customers">
    <div x-data="{ open: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-6">
        <x-admin.page-head eyebrow="Clientele" title="Customers">
            <button type="button" @click="open = true" class="btn btn-sm"><x-lucide-plus class="h-3.5 w-3.5" />Add client</button>
        </x-admin.page-head>
        <div class="grid gap-4 sm:grid-cols-3">
            <x-admin.kpi label="Clients" :value="$stats['count']" />
            <x-admin.kpi label="Obsidian Circle VIPs" :value="$stats['vip']" />
            <x-admin.kpi label="Avg lifetime value" :value="'$'.number_format($stats['avg_ltv'])" />
        </div>
        <x-admin.panel title="Client directory">
            <x-slot:action>
                <form method="GET" class="flex items-center gap-2">
                    <select name="tier" onchange="this.form.submit()" class="input py-1.5 text-xs" aria-label="Filter by tier"><option value="">All tiers</option>@foreach (config('store.tiers') as $t)<option @selected($tier === $t)>{{ $t }}</option>@endforeach</select>
                    <input name="q" value="{{ $search }}" placeholder="Search" aria-label="Search clients" class="input w-48 py-1.5 text-xs">
                </form>
            </x-slot:action>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Client</th><th>Circle tier</th><th>City</th><th>Orders</th><th>Lifetime value</th><th>Joined</th></tr></thead>
                    <tbody>
                        @forelse ($customers as $customer)
                            <tr>
                                <td><a href="{{ route('admin.customers.show', $customer) }}" class="font-medium underline">{{ $customer->name }}</a><div class="text-xs text-muted-foreground">{{ $customer->email }}</div></td>
                                <td><x-admin.status :s="$customer->tier" /></td>
                                <td>{{ $customer->city ?? '—' }}</td>
                                <td>{{ $customer->orders_count }}</td>
                                <td class="font-mono">${{ number_format((float) $customer->lifetime_value, 2) }}</td>
                                <td class="text-muted-foreground">{{ $customer->created_at->format('d M Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted-foreground">No clients found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $customers->links() }}
        </x-admin.panel>

        <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-obsidian/40" @click="open = false"></div>
            <form method="POST" action="{{ route('admin.customers.store') }}" class="relative w-full max-w-md space-y-4 rounded-2xl bg-card p-6">
                @csrf
                <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold">Add client</h2><button type="button" @click="open = false" aria-label="Close"><x-lucide-x class="h-4 w-4" /></button></div>
                <x-admin.field label="Full name" name="name"><input name="name" value="{{ old('name') }}" required class="input"></x-admin.field>
                <x-admin.field label="Email" name="email"><input type="email" name="email" value="{{ old('email') }}" required class="input"></x-admin.field>
                <div class="grid grid-cols-2 gap-3">
                    <x-admin.field label="Phone" name="phone"><input name="phone" value="{{ old('phone') }}" class="input"></x-admin.field>
                    <x-admin.field label="City" name="city"><input name="city" value="{{ old('city') }}" class="input"></x-admin.field>
                </div>
                <x-admin.field label="Circle tier" name="tier"><select name="tier" class="input">@foreach (config('store.tiers') as $t)<option @selected(old('tier') === $t)>{{ $t }}</option>@endforeach</select></x-admin.field>
                <p class="text-xs text-muted-foreground">The client can set their own password later via password reset; until then they shop via concierge orders.</p>
                <button class="btn w-full py-2.5">Create client</button>
            </form>
        </div>
    </div>
</x-layouts.admin>
