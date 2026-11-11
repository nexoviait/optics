<x-layouts.admin title="Payments">
    <div x-data="{ open: null }" class="space-y-6">
        <x-admin.page-head eyebrow="Finance" title="Payments">
            <a href="{{ route('admin.analytics.export', 'payments') }}" class="btn-ghost btn-sm"><x-lucide-download class="h-3.5 w-3.5" />Export CSV</a>
        </x-admin.page-head>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.kpi label="Settled revenue" :value="'$'.number_format($kpis['succeeded'], 2)" />
            <x-admin.kpi label="In-transit / pending" :value="'$'.number_format($kpis['pending'], 2)" />
            <x-admin.kpi label="Refunds" :value="'$'.number_format($kpis['refunded'], 2)" />
            <x-admin.kpi label="Failed / declined" :value="$kpis['failed']" :warn="$kpis['failed'] > 0" />
        </div>

        <x-admin.panel title="Checkout payment methods">
            <x-slot:action><a href="{{ route('admin.payments.methods.index') }}" class="btn btn-sm"><x-lucide-settings-2 class="h-3.5 w-3.5" />Manage methods</a></x-slot:action>
            <div class="flex flex-wrap gap-2">
                @foreach ($methods as $method)
                    <span @class(['flex items-center gap-2 rounded-full border border-border px-3 py-1.5 text-xs', 'opacity-50' => ! $method->is_active])>
                        @if ($method->logoUrl())<img src="{{ $method->logoUrl() }}" alt="" class="h-4 w-auto max-w-10 object-contain">@endif
                        {{ $method->name }}<x-admin.status :s="$method->is_active ? 'Active' : 'Draft'" />
                    </span>
                @endforeach
            </div>
            <p class="mt-3 text-xs text-muted-foreground">Concierge orders can additionally be settled by phone, payment link, cash on delivery or boutique terminal. No live payment gateway is connected yet — methods marked "Succeeded on placement" are recorded as paid immediately; the rest stay pending until you confirm them below.</p>
        </x-admin.panel>

        <x-admin.panel title="Transaction ledger">
            <form method="GET" class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex rounded-full bg-secondary p-1 text-xs">
                    @foreach (array_merge([''], config('store.payment_statuses')) as $s)
                        <a href="{{ route('admin.payments.index', array_filter(['status' => $s ?: null, 'q' => $search ?: null, 'date' => $date])) }}" @class(['rounded-full px-3 py-1', 'bg-primary text-primary-foreground' => ($status ?? '') === $s])>{{ $s ?: 'All' }}</a>
                    @endforeach
                </div>
                @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="input w-auto" aria-label="Date">
                <div class="relative ml-auto w-64"><x-lucide-search class="absolute left-3 top-2.5 h-4 w-4 text-muted-foreground" /><input name="q" value="{{ $search }}" placeholder="Search transactions" aria-label="Search transactions" class="input pl-9"></div>
            </form>
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>Transaction</th><th>Order</th><th>Customer</th><th>Method</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr @click="open = {{ $payment->id }}" class="cursor-pointer hover:bg-secondary">
                                <td class="font-mono text-xs">{{ $payment->reference }}</td>
                                <td class="font-mono text-xs">{{ $payment->order?->order_number }}</td>
                                <td>{{ $payment->order?->customerName() }}</td>
                                <td class="text-xs">{{ $payment->methodLabel() }}</td>
                                <td>${{ number_format((float) $payment->amount, 2) }}</td>
                                <td class="text-muted-foreground">{{ $payment->created_at->format('d M Y') }}</td>
                                <td><x-admin.status :s="$payment->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted-foreground">No transactions found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $payments->links() }}
        </x-admin.panel>

        @foreach ($payments as $payment)
            <div x-show="open === {{ $payment->id }}" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="open = null">
                <div class="absolute inset-0 bg-obsidian/40" @click="open = null"></div>
                <aside class="absolute inset-y-0 right-0 w-full space-y-4 overflow-y-auto bg-background p-5 text-sm sm:max-w-md">
                    <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold">{{ $payment->reference }}</h2><button type="button" @click="open = null" aria-label="Close"><x-lucide-x class="h-4 w-4" /></button></div>
                    <div class="flex items-center justify-between"><span class="font-display text-3xl font-semibold">${{ number_format((float) $payment->amount, 2) }}</span><x-admin.status :s="$payment->status" /></div>
                    <x-admin.panel title="Associated order">@if ($payment->order)<a href="{{ route('admin.orders.show', $payment->order) }}" class="font-mono underline">{{ $payment->order->order_number }}</a><p class="mt-1">{{ $payment->order->customerName() }}</p>@endif</x-admin.panel>
                    <x-admin.panel title="Payment instrument">
                        <p>{{ $payment->methodLabel() }}</p>
                        @if ($payment->payer_account || $payment->payer_reference)
                            <dl class="mt-2 space-y-1 text-xs">
                                @if ($payment->payer_account)<div class="flex justify-between"><dt class="text-muted-foreground">Payer account</dt><dd class="font-mono">{{ $payment->payer_account }}</dd></div>@endif
                                @if ($payment->payer_reference)<div class="flex justify-between"><dt class="text-muted-foreground">Transaction ID</dt><dd class="font-mono">{{ $payment->payer_reference }}</dd></div>@endif
                            </dl>
                        @endif
                    </x-admin.panel>
                    <x-admin.panel title="Fee reconciliation">
                        <dl class="space-y-1">
                            <div class="flex justify-between"><dt class="text-muted-foreground">Gross</dt><dd class="font-mono">${{ number_format((float) $payment->amount, 2) }}</dd></div>
                            <div class="flex justify-between"><dt class="text-muted-foreground">Processing fee</dt><dd class="font-mono">-${{ number_format((float) $payment->fee, 2) }}</dd></div>
                            <div class="flex justify-between font-semibold"><dt>Net payout</dt><dd class="font-mono">${{ number_format($payment->netPayout(), 2) }}</dd></div>
                        </dl>
                    </x-admin.panel>
                    <x-admin.panel title="Audit trail">
                        <ul class="space-y-1 font-mono text-xs">
                            <li class="flex gap-3"><span class="text-muted-foreground">{{ $payment->created_at->format('d M H:i') }}</span>payment.created</li>
                            @if ($payment->refunded_at)<li class="flex gap-3"><span class="text-muted-foreground">{{ $payment->refunded_at->format('d M H:i') }}</span>payment.refunded</li>@endif
                        </ul>
                    </x-admin.panel>
                    @if ($payment->status === 'Pending')
                        <form method="POST" action="{{ route('admin.payments.confirm', $payment) }}" onsubmit="return confirm('Mark this payment of ${{ number_format((float) $payment->amount, 2) }} as received?')">@csrf<button class="btn">Mark as received</button></form>
                    @endif
                    @if ($payment->status === 'Succeeded')
                        <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" onsubmit="return confirm('Record a refund of ${{ number_format((float) $payment->amount, 2) }}?')">@csrf<button class="btn">Issue refund</button></form>
                    @endif
                </aside>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
