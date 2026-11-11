<x-layouts.admin title="Analytics & Reports">
    <x-admin.page-head eyebrow="Reports" title="Analytics & Reports" />
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-admin.kpi label="Sales (30d)" :value="'$'.number_format($summary['sales'] / 1000, 1).'k'" :delta="$summary['sales_delta'] === null ? null : ($summary['sales_delta'] >= 0 ? '+' : '').$summary['sales_delta'].'%'" />
        <x-admin.kpi label="Net after fees (30d)" :value="'$'.number_format($net / 1000, 1).'k'" />
        <x-admin.kpi label="Orders (30d)" :value="$summary['orders']" />
        <x-admin.kpi label="AOV" :value="'$'.number_format($summary['aov'])" />
        <x-admin.kpi label="Refund rate (all time)" :value="$refundRate.'%'" />
    </div>
    @if ($lowFrames || $lowLenses)
        <div class="flex items-center gap-3 rounded-xl border border-destructive/40 bg-card p-4 text-sm"><x-lucide-triangle-alert class="h-4 w-4 text-destructive" />{{ $lowFrames }} {{ Str::plural('frame', $lowFrames) }} and {{ $lowLenses }} lens {{ Str::plural('blank', $lowLenses) }} are below the low-stock threshold.</div>
    @endif

    <div x-data="salesChart(@js($series->values()), { combo: true })">
        <x-admin.panel title="Revenue & velocity trajectory (30 days)">
            <x-slot:action>
                <div class="flex rounded-full bg-secondary p-1 text-xs">
                    @foreach (['daily' => 'Daily', 'weekly' => 'Weekly'] as $key => $label)
                        <a href="{{ route('admin.analytics.index', ['view' => $key]) }}" @class(['rounded-full px-3 py-1', 'bg-primary text-primary-foreground' => $view === $key])>{{ $label }}</a>
                    @endforeach
                </div>
            </x-slot:action>
            <div class="h-72"><canvas x-ref="canvas" role="img" aria-label="Revenue chart"></canvas></div>
        </x-admin.panel>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-admin.panel title="Financial reconciliation (30d)">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-muted-foreground">Gross before discounts</dt><dd class="font-mono">${{ number_format($reconciliation['gross'], 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Discounts</dt><dd class="font-mono">-${{ number_format($reconciliation['discounts'], 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Refunds issued</dt><dd class="font-mono">-${{ number_format($reconciliation['refunds'], 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Processing fees</dt><dd class="font-mono">-${{ number_format($reconciliation['fees'], 2) }}</dd></div>
                <div class="flex justify-between border-t border-border pt-2 font-semibold"><dt>Net balance</dt><dd class="font-mono">${{ number_format($reconciliation['gross'] - $reconciliation['discounts'] - $reconciliation['refunds'] - $reconciliation['fees'], 2) }}</dd></div>
            </dl>
        </x-admin.panel>
        <x-admin.panel title="Customer distribution">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-muted-foreground">Purchasing clients</dt><dd>{{ $customerMix['buyers'] }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Repeat purchase rate</dt><dd>{{ $customerMix['repeat_rate'] }}%</dd></div>
                <div class="flex justify-between"><dt class="text-muted-foreground">Avg lifetime value</dt><dd>${{ number_format($customerMix['avg_ltv'], 2) }}</dd></div>
            </dl>
            <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-secondary"><div class="bg-bronze" style="width: {{ $customerMix['repeat_rate'] }}%"></div></div>
            <div class="mt-2 flex justify-between text-xs"><span>Returning {{ $customerMix['repeat_rate'] }}%</span><span>One-time {{ 100 - $customerMix['repeat_rate'] }}%</span></div>
        </x-admin.panel>
        <x-admin.panel title="VisionMatch AR conversion">
            <p class="font-display text-3xl font-semibold">{{ $visionmatch['conversion'] }}%</p>
            <p class="text-xs text-muted-foreground">Match sessions that led to an add-to-bag</p>
            <p class="mt-4 text-sm">Total sessions <span class="float-right font-mono">{{ number_format($visionmatch['sessions']) }}</span></p>
        </x-admin.panel>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.4fr_1fr]" x-data="{ sku: 'frames' }">
        <x-admin.panel title="Top performing optical SKUs">
            <x-slot:action>
                <div class="flex rounded-full bg-secondary p-1 text-xs">
                    <button type="button" @click="sku = 'frames'" :class="sku === 'frames' && 'bg-primary text-primary-foreground'" class="rounded-full px-3 py-1">Frames</button>
                    <button type="button" @click="sku = 'lenses'" :class="sku === 'lenses' && 'bg-primary text-primary-foreground'" class="rounded-full px-3 py-1">Lenses</button>
                </div>
            </x-slot:action>
            <div class="table-scroll">
                <table class="data-table" x-show="sku === 'frames'">
                    <thead><tr><th>Frame</th><th>Units</th><th>Gross revenue</th></tr></thead>
                    <tbody>@forelse ($topFrames as $row)<tr><td>{{ $row->name }}</td><td>{{ (int) $row->units }}</td><td class="font-mono">${{ number_format((float) $row->revenue, 2) }}</td></tr>@empty<tr><td colspan="3" class="text-muted-foreground">No sales yet.</td></tr>@endforelse</tbody>
                </table>
                <table class="data-table" x-show="sku === 'lenses'" x-cloak>
                    <thead><tr><th>Lens option</th><th>Units</th></tr></thead>
                    <tbody>@forelse ($topLenses as $row)<tr><td>{{ $row['name'] }}</td><td>{{ $row['units'] }}</td></tr>@empty<tr><td colspan="2" class="text-muted-foreground">No lens sales yet.</td></tr>@endforelse</tbody>
                </table>
            </div>
        </x-admin.panel>
        <div class="space-y-6">
            <x-admin.panel title="Fulfillment operations">
                @foreach ($fulfilment as $label => $pct)
                    <div class="mb-3"><div class="flex justify-between text-xs"><span>{{ $label }}</span><span>{{ $pct }}%</span></div><div class="mt-1 h-2 rounded bg-secondary"><div class="h-full rounded bg-primary" style="width: {{ $pct }}%"></div></div></div>
                @endforeach
            </x-admin.panel>
            <x-admin.panel title="Export dossiers (CSV)">
                @foreach (['sales' => 'Daily Sales Summary (30d)', 'orders' => 'Orders Ledger', 'payments' => 'Payments & Fees Report'] as $report => $label)
                    <a href="{{ route('admin.analytics.export', $report) }}" class="mb-2 flex w-full items-center justify-between rounded-lg border border-border p-3 text-sm hover:border-bronze">{{ $label }}<x-lucide-download class="h-4 w-4" /></a>
                @endforeach
            </x-admin.panel>
        </div>
    </div>
</x-layouts.admin>
