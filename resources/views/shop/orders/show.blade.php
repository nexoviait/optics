{{-- Order detail & tracking. $owner = signed-in customer; otherwise opened via a signed tracking link, so personal details are trimmed. --}}
@php
    $timeline = $order->timeline();
    $courierUrl = $order->trackingUrl();
@endphp
<x-layouts.app :title="'Order '.$order->order_number">
    <div class="mx-auto max-w-4xl px-5 py-12">
        @if ($owner)<a href="{{ route('account') }}" class="text-xs text-muted-foreground hover:underline">← My orders</a>@endif
        <div class="mt-2 flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow text-muted-foreground">Placed {{ $order->created_at->format('j M Y') }}</p>
                <h1 class="mt-1 text-3xl font-medium">Order <span class="font-mono">{{ $order->order_number }}</span></h1>
            </div>
            <x-admin.status :s="$order->status" />
        </div>

        {{-- Status headline --}}
        <section @class(['mt-8 rounded-2xl border bg-card p-6', 'border-destructive/40' => $order->isCancelled(), 'border-border' => ! $order->isCancelled()])>
            <h2 class="text-xl font-semibold">{{ $order->statusLabel() }}</h2>
            <p class="mt-1 text-sm text-muted-foreground">{{ $order->statusDescription() }}</p>
            @if (! $order->isCancelled() && $order->status !== 'Delivered' && $order->delivery_from)
                <p class="mt-3 text-sm">Estimated delivery: <strong>{{ $order->delivery_from->format('D j M') }} – {{ $order->delivery_to->format('D j M') }}</strong></p>
            @endif
            @if ($order->delivered_at)<p class="mt-3 text-sm">Delivered on <strong>{{ $order->delivered_at->format('D j M Y') }}</strong></p>@endif
            @if ($order->tracking_number)
                <div class="mt-4 flex flex-wrap items-center gap-3 rounded-xl bg-secondary p-4 text-sm">
                    <x-lucide-truck class="h-5 w-5" />
                    <div><p class="text-xs text-muted-foreground">{{ $order->carrier }}</p><p class="font-mono font-semibold">{{ $order->tracking_number }}</p></div>
                    @if ($courierUrl)<a href="{{ $courierUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm ml-auto">Track with {{ $order->carrier }} ↗</a>@endif
                </div>
            @endif

            @unless ($order->isCancelled())
                <ol class="mt-6 grid gap-4 sm:grid-cols-5" aria-label="Order progress">
                    @foreach ($timeline as $step)
                        <li class="flex gap-3 sm:block" @if ($step['current']) aria-current="step" @endif>
                            <span @class([
                                'grid h-7 w-7 shrink-0 place-items-center rounded-full text-xs',
                                'bg-primary text-primary-foreground' => $step['reached'],
                                'bg-muted text-muted-foreground' => ! $step['reached'],
                            ])>@if ($step['reached'])<x-lucide-check class="h-3.5 w-3.5" />@else{{ $loop->iteration }}@endif</span>
                            <div class="sm:mt-2">
                                <p @class(['text-sm', 'font-semibold' => $step['current'], 'text-muted-foreground' => ! $step['reached']])>{{ $step['label'] }}</p>
                                <p class="text-xs text-muted-foreground">{{ $step['at']?->format('j M, H:i') ?? ($step['reached'] ? '' : 'Pending') }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @else
                <p class="mt-3 text-sm">Cancelled on <strong>{{ ($order->cancelled_at ?? $order->updated_at)->format('j M Y') }}</strong>.</p>
            @endunless
        </section>

        <div class="mt-6 grid gap-6 md:grid-cols-[1.4fr_1fr]">
            <section class="rounded-2xl border border-border bg-card p-6">
                <h2 class="font-semibold">Items</h2>
                <ul class="mt-4 divide-y divide-border">
                    @foreach ($order->items as $item)
                        <li class="flex gap-4 py-3">
                            @if ($item->product)<img src="{{ $item->product->imageUrl() }}" alt="" class="h-16 w-20 rounded-lg object-cover">@endif
                            <div class="min-w-0 flex-1 text-sm">
                                <p class="font-medium">{{ $item->qty }}× {{ $item->name }}</p>
                                <p class="text-xs text-muted-foreground">{{ $item->finish }}{{ $item->frame_size ? ' · Size '.$item->frame_size : '' }}{{ $item->rx_mode === 'later' ? ' · Prescription awaited' : '' }}</p>
                            </div>
                            <span class="font-mono text-sm">${{ number_format((float) $item->unit_price * $item->qty, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-3 space-y-1 border-t border-border pt-3 text-sm">
                    <div class="flex justify-between"><dt class="text-muted-foreground">Subtotal</dt><dd>${{ number_format((float) $order->subtotal, 2) }}</dd></div>
                    @if ((float) $order->member_discount > 0)<div class="flex justify-between"><dt class="text-muted-foreground">Member discount</dt><dd>-${{ number_format((float) $order->member_discount, 2) }}</dd></div>@endif
                    @if ((float) $order->gift_discount > 0)<div class="flex justify-between"><dt class="text-muted-foreground">Code {{ $order->coupon_code }}</dt><dd>-${{ number_format((float) $order->gift_discount, 2) }}</dd></div>@endif
                    <div class="flex justify-between font-semibold"><dt>Total</dt><dd>${{ number_format((float) $order->total, 2) }}</dd></div>
                    <div class="flex justify-between text-xs text-muted-foreground"><dt>{{ $order->paymentLabel() }}</dt><dd>{{ $order->payment?->status === 'Refunded' ? 'Refunded' : ($order->isPaid() ? 'Paid' : 'Payment due') }}</dd></div>
                </dl>
            </section>

            <div class="space-y-6">
                <section class="rounded-2xl border border-border bg-card p-6 text-sm">
                    <h2 class="font-semibold">Delivery address</h2>
                    @if (! $order->hasShippingAddress())
                        <p class="mt-2 text-muted-foreground">Collected in boutique or arranged with our concierge.</p>
                    @elseif ($owner)
                        <address class="mt-2 not-italic leading-relaxed">@foreach ($order->shippingAddressLines() as $line){{ $line }}<br>@endforeach</address>
                        <p class="mt-1 text-xs text-muted-foreground">{{ $order->ship_phone }}</p>
                        @if ($order->customer_note)<p class="mt-2 text-xs">Note: {{ $order->customer_note }}</p>@endif
                    @else
                        <p class="mt-2">{{ $order->ship_city }}, {{ config("store.delivery_regions.{$order->ship_country}.name", $order->ship_country) }}</p>
                        <p class="mt-1 text-xs text-muted-foreground"><a href="{{ route('login') }}" class="underline">Sign in</a> to see full order details.</p>
                    @endif
                </section>

                <section class="rounded-2xl border border-border bg-card p-6 text-sm">
                    <h2 class="font-semibold">Need help?</h2>
                    <p class="mt-1 text-muted-foreground">Our concierge can help with prescriptions, changes and returns.</p>
                    @if (! empty($store['concierge_email']))<a href="mailto:{{ $store['concierge_email'] }}?subject={{ rawurlencode('Order '.$order->order_number) }}" class="mt-3 inline-block underline">{{ $store['concierge_email'] }}</a>@endif
                    @if ($owner && $order->isCancellableByCustomer())
                        <form method="POST" action="{{ route('orders.cancel', $order) }}" class="mt-4 border-t border-border pt-4" onsubmit="return confirm(@js('Cancel order '.$order->order_number.'? Any payment will be refunded.'))">
                            @csrf
                            <button class="text-sm text-destructive underline">Cancel this order</button>
                            <p class="mt-1 text-xs text-muted-foreground">Free cancellation until an optician starts work on your order.</p>
                        </form>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-layouts.app>
