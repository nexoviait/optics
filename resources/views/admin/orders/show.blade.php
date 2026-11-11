@php
    $pipeline = config('store.order_pipeline');
    $index = array_search($order->status, $pipeline, true);
    $next = $order->nextStatus();
    $carriers = array_keys(config('store.carriers'));
    $closed = in_array($order->status, ['Cancelled', 'Delivered'], true);
@endphp
<x-layouts.admin :title="'Order '.$order->order_number">
    <x-admin.page-head :eyebrow="$order->created_at->format('d M Y, H:i')" :title="'Order '.$order->order_number">
        <a href="{{ route('admin.orders.index') }}" class="btn-ghost btn-sm">Back</a>
        @unless ($closed)
            <form method="POST" action="{{ route('admin.orders.status', $order) }}" onsubmit="return confirm('Cancel this order, return stock and refund any payment taken?')">@csrf @method('PATCH')<input type="hidden" name="status" value="Cancelled"><button class="btn-ghost btn-sm text-destructive">Cancel order</button></form>
        @endunless
        @if ($next && $next !== 'Shipped')
            <form method="POST" action="{{ route('admin.orders.status', $order) }}">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $next }}"><button class="btn btn-sm">Advance to {{ $next }}</button></form>
        @endif
    </x-admin.page-head>

    <x-admin.panel title="Fulfilment">
        <div class="flex flex-wrap gap-2">
            @foreach ($pipeline as $i => $stage)
                <div @class(['flex flex-1 items-center gap-2 rounded-lg border px-3 py-2 text-xs', 'border-primary bg-secondary' => $index !== false && $i <= $index, 'border-border' => $index === false || $i > $index])>
                    @if ($index !== false && $i < $index)<x-lucide-check class="h-3 w-3" />@else<span class="font-mono">{{ $i + 1 }}</span>@endif{{ $stage }}
                </div>
            @endforeach
        </div>
        <p class="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">Channel: {{ $order->channel }} · Payment: {{ $order->paymentLabel() }} @if ($order->payment)({{ $order->payment->reference }}, {{ $order->payment->status }})@if ($order->payment->payer_reference) · From <span class="font-mono">{{ $order->payment->payer_account }}</span> · TrxID <span class="font-mono">{{ $order->payment->payer_reference }}</span>@endif @endif · Current: <x-admin.status :s="$order->status" /></p>

        @if ($next === 'Shipped' || ($order->status === 'Shipped'))
            <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-4 grid gap-3 rounded-lg border border-border p-4 md:grid-cols-[1fr_1.4fr_auto] md:items-end">
                @csrf @method('PATCH')
                <input type="hidden" name="status" value="Shipped">
                <x-admin.field label="Courier" name="carrier">
                    <select name="carrier" required class="input">@foreach ($carriers as $c)<option @selected(old('carrier', $order->carrier) === $c)>{{ $c }}</option>@endforeach</select>
                </x-admin.field>
                <x-admin.field label="Tracking number" name="tracking_number"><input name="tracking_number" value="{{ old('tracking_number', $order->tracking_number) }}" required maxlength="60" pattern="[A-Za-z0-9 \-]+" class="input font-mono"></x-admin.field>
                <button class="btn btn-sm">{{ $order->status === 'Shipped' ? 'Update tracking' : 'Mark as shipped & notify' }}</button>
            </form>
        @elseif ($order->tracking_number)
            <p class="mt-3 text-xs">Tracking: {{ $order->carrier }} · <span class="font-mono">{{ $order->tracking_number }}</span>@if ($order->trackingUrl()) · <a href="{{ $order->trackingUrl() }}" target="_blank" rel="noopener noreferrer" class="underline">open ↗</a>@endif</p>
        @endif
    </x-admin.panel>

    <div class="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div class="space-y-6">
            <x-admin.panel title="Frames, lenses & prescriptions">
                <div class="space-y-3">
                    @foreach ($order->items as $item)
                        @php($rx = $item->prescription)
                        <div class="rounded-lg border border-border p-3 text-sm">
                            <div class="flex justify-between"><span class="font-semibold">{{ $item->qty }}× {{ $item->name }}</span><span class="font-mono">${{ number_format((float) $item->unit_price, 2) }}</span></div>
                            <p class="text-xs text-muted-foreground">{{ $item->finish }}{{ $item->frame_size ? ' · Size '.$item->frame_size : '' }} · Power: {{ $item->power_type ?? '—' }} · Lens: {{ $lensLabels[$item->lens] ?? $item->lens ?? '—' }} · Rx: {{ $item->rx_mode ?? '—' }}</p>
                            @if ($rx)
                                @if ($rx->source === 'upload')
                                    <p class="mt-2 text-xs">Uploaded file: <a href="{{ route('admin.prescriptions.file', $rx) }}" target="_blank" rel="noopener" class="underline">{{ $rx->file_name }}</a></p>
                                @else
                                    <div class="mt-2 grid grid-cols-2 gap-2 font-mono text-xs sm:grid-cols-4">
                                        @foreach (['OD SPH' => $rx->r_sph, 'OD CYL×AX' => ($rx->r_cyl ?? '—').' × '.($rx->r_axis ?? '—'), 'OS SPH' => $rx->l_sph, 'OS CYL×AX' => ($rx->l_cyl ?? '—').' × '.($rx->l_axis ?? '—'), 'ADD' => $rx->add_power, 'PD' => $rx->pd ? $rx->pd.' mm' : null] as $k => $v)
                                            <div class="rounded bg-secondary p-2"><div class="text-muted-foreground">{{ $k }}</div>{{ $v ?? '—' }}</div>
                                        @endforeach
                                    </div>
                                    @if ($rx->patient_name)<p class="mt-2 text-xs text-muted-foreground">Patient: {{ $rx->patient_name }} · {{ $rx->phone }}</p>@endif
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-admin.panel>

            <x-admin.panel title="Status history">
                <ol class="space-y-3 text-sm">
                    @forelse ($order->events as $event)
                        <li class="flex gap-3">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2"><x-admin.status :s="$event->status" /><span class="text-xs text-muted-foreground">{{ $event->created_at->format('j M Y, H:i') }} · {{ $event->user?->isAdmin() ? $event->user->name : ($event->user ? 'Customer' : 'System') }}</span></div>
                                @if ($event->note)<p class="mt-1 text-xs">{{ $event->note }}</p>@endif
                            </div>
                        </li>
                    @empty
                        <li class="text-xs text-muted-foreground">No history recorded for this order (placed before status tracking was enabled).</li>
                    @endforelse
                </ol>
                @unless ($order->isCancelled())
                    <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="mt-5 grid gap-2 border-t border-border pt-4 sm:grid-cols-[auto_1fr_auto] sm:items-end">
                        @csrf @method('PATCH')
                        <x-admin.field label="Set status" name="status">
                            <select name="status" class="input py-1.5 text-xs">@foreach (config('store.order_statuses') as $s)@continue($s === 'Shipped' && ! $order->tracking_number)<option @selected($order->status === $s)>{{ $s }}</option>@endforeach</select>
                        </x-admin.field>
                        <x-admin.field label="Note for the customer (optional)" name="note"><input name="note" maxlength="255" class="input py-1.5 text-xs"></x-admin.field>
                        <button class="btn btn-sm">Update</button>
                    </form>
                    <p class="mt-2 text-[0.65rem] text-muted-foreground">Forward moves and cancellations email the customer. Use the shipping form above to mark the order as shipped.</p>
                @endunless
            </x-admin.panel>
        </div>

        <div class="space-y-6">
            <x-admin.panel title="Customer">
                <p class="flex items-center gap-2 font-semibold">{{ $order->customerName() }}@if ($order->isGuest())<span class="rounded-full bg-secondary px-2 py-0.5 text-[0.6rem] font-normal">Guest checkout</span>@endif</p>
                @if ($order->contactEmail())<a href="mailto:{{ $order->contactEmail() }}" class="text-sm text-muted-foreground hover:underline">{{ $order->contactEmail() }}</a>@endif
                @if ($order->user && ! $order->user->isAdmin())<a href="{{ route('admin.customers.show', $order->user) }}" class="mt-2 block text-xs underline">View client profile</a>@endif
            </x-admin.panel>

            <x-admin.panel title="Ship to">
                @if ($order->hasShippingAddress())
                    <address class="text-sm not-italic leading-relaxed">@foreach ($order->shippingAddressLines() as $line){{ $line }}<br>@endforeach</address>
                    <p class="mt-1 text-xs text-muted-foreground">{{ $order->ship_phone }}</p>
                    @if ($order->customer_note)<p class="mt-3 rounded-lg bg-bronze-soft p-2 text-xs">Customer note: {{ $order->customer_note }}</p>@endif
                    @if ($order->delivery_from)<p class="mt-3 text-xs">Promised window: {{ $order->delivery_from->format('j M') }} – {{ $order->delivery_to->format('j M Y') }}</p>@endif
                @else
                    <p class="text-sm text-muted-foreground">No delivery address (concierge / boutique order).</p>
                @endif
            </x-admin.panel>

            <x-admin.panel title="Totals">
                <dl class="space-y-1 text-sm">
                    <div class="flex justify-between"><dt class="text-muted-foreground">Subtotal</dt><dd class="font-mono">${{ number_format((float) $order->subtotal, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">Member discount</dt><dd class="font-mono">-${{ number_format((float) $order->member_discount, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-muted-foreground">Coupon {{ $order->coupon_code ? '('.$order->coupon_code.')' : '' }}</dt><dd class="font-mono">-${{ number_format((float) $order->gift_discount, 2) }}</dd></div>
                </dl>
                <p class="mt-3 font-display text-xl font-semibold">${{ number_format((float) $order->total, 2) }}</p>
            </x-admin.panel>
        </div>
    </div>
</x-layouts.admin>
