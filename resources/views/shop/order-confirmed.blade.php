<x-layouts.app :title="'Order '.$order->order_number.' confirmed'">
    <div class="mx-auto max-w-3xl px-5 py-16">
        <div class="text-center">
            <div class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-sage-soft"><x-lucide-check class="h-6 w-6" /></div>
            <p class="eyebrow mt-6 text-muted-foreground">Thank you, {{ strtok($order->customerName(), ' ') }}</p>
            <h1 class="mt-2 text-3xl font-medium">Your order is confirmed</h1>
            <p class="mt-3 text-sm text-muted-foreground">
                Order <span class="font-mono font-semibold text-foreground">{{ $order->order_number }}</span>
                @if ($order->contactEmail()) · A confirmation has been sent to <span class="font-medium text-foreground">{{ $order->contactEmail() }}</span>.@endif
            </p>
            @unless ($order->contactEmail())
                <p class="mx-auto mt-4 max-w-md rounded-xl bg-bronze-soft p-3 text-xs">You didn’t add an email, so we won’t send updates. <strong>Save your order number {{ $order->order_number }}</strong> — you can track this order any time with it and your phone number at <a href="{{ route('orders.track') }}" class="underline">Track order</a>, or bookmark the tracking link below.</p>
            @endunless
        </div>

        <div class="mt-10 grid gap-5 md:grid-cols-2">
            <section class="rounded-2xl border border-border bg-card p-5 text-sm">
                <h2 class="eyebrow text-[0.6rem] text-muted-foreground">Delivery</h2>
                @if ($order->delivery_from)
                    <p class="mt-2 text-lg font-semibold">{{ $order->delivery_from->format('D j M') }} – {{ $order->delivery_to->format('D j M') }}</p>
                    <p class="text-xs text-muted-foreground">Estimated arrival, after prescription check and lens surfacing.</p>
                @endif
                @if ($order->hasShippingAddress())
                    <address class="mt-3 not-italic leading-relaxed">@foreach ($order->shippingAddressLines() as $line){{ $line }}<br>@endforeach</address>
                    <p class="mt-1 text-xs text-muted-foreground">{{ $order->ship_phone }}</p>
                @endif
            </section>
            <section class="rounded-2xl border border-border bg-card p-5 text-sm">
                <h2 class="eyebrow text-[0.6rem] text-muted-foreground">Payment</h2>
                <p class="mt-2 text-lg font-semibold">${{ number_format((float) $order->total, 2) }}</p>
                <p class="text-xs text-muted-foreground">{{ $order->paymentLabel() }} · {{ $order->isPaid() ? 'Paid' : 'Payment due' }}</p>
                <ul class="mt-3 space-y-1 border-t border-border pt-3">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3"><span>{{ $item->qty }}× {{ $item->name }}{{ $item->frame_size ? ' · '.$item->frame_size : '' }}</span><span class="font-mono">${{ number_format((float) $item->unit_price * $item->qty, 2) }}</span></li>
                    @endforeach
                </ul>
            </section>
        </div>

        <section class="mt-5 rounded-2xl border border-border bg-card p-5">
            <h2 class="font-semibold">What happens next</h2>
            <ol class="mt-4 grid gap-4 text-sm sm:grid-cols-4">
                @foreach (array_slice($order->timeline(), 1) as $i => $step)
                    <li><span class="grid h-6 w-6 place-items-center rounded-full bg-secondary font-mono text-xs">{{ $i + 1 }}</span><p class="mt-2 font-medium">{{ $step['label'] }}</p><p class="mt-1 text-xs text-muted-foreground">{{ $step['description'] }}</p></li>
                @endforeach
            </ol>
            @if ($order->items->contains('rx_mode', 'later'))
                <p class="mt-4 rounded-lg bg-bronze-soft p-3 text-xs">Please send your prescription within {{ $store['rx_submission_days'] ?? 15 }} days by replying to your confirmation email — we’ll start your lenses as soon as it arrives.</p>
            @endif
        </section>

        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ $order->isGuest() ? $order->publicTrackingUrl() : route('orders.show', $order) }}" class="btn px-6 py-3">Track this order</a>
            <a href="{{ route('home') }}" class="btn-ghost px-6 py-3">Continue shopping</a>
        </div>
        @if ($order->isGuest())
            <p class="mt-4 text-center text-xs text-muted-foreground">Track this order any time from the link above or at <a href="{{ route('orders.track') }}" class="underline">Track order</a> with your order number and email or phone. To change or cancel, contact {{ $store['concierge_email'] ?? 'our concierge' }}.</p>
        @else
            <p class="mt-4 text-center text-xs text-muted-foreground">Changed your mind? You can cancel from the order page until an optician starts work on it.</p>
        @endif
    </div>
</x-layouts.app>
