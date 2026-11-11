@php
    $storeName = \App\Support\Settings::get('store_name', config('app.name'));
    $hasCta = collect($blocks)->contains('type', 'CTA Button');
    $money = fn ($v) => '$'.number_format((float) $v, 2);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $order->statusLabel() }} · {{ $order->order_number }}</title>
</head>
<body style="margin:0;padding:0;background:#F8F6EE;font-family:Helvetica,Arial,sans-serif;color:#0F1113;">
    <span style="display:none;max-height:0;overflow:hidden;">{{ $preheader }}</span>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8F6EE;padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #E7E3D8;border-radius:12px;">
                <tr><td style="padding:28px 28px 8px;">
                    @foreach ($blocks as $block)
                        @switch($block['type'])
                            @case('Logo')
                                <p style="margin:0 0 20px;font-size:14px;font-weight:bold;letter-spacing:4px;">{{ strtoupper(strtok($storeName, ' ')) }}</p>
                                @break
                            @case('Heading')
                                <h1 style="margin:0 0 12px;font-size:22px;">{{ $block['text'] }}</h1>
                                @break
                            @case('Paragraph')
                                <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#4A4D50;">{{ $block['text'] }}</p>
                                @break
                            @case('CTA Button')
                                <p style="margin:20px 0;"><a href="{{ $trackUrl }}" style="display:inline-block;background:#0F1113;color:#ffffff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:14px;">{{ $block['text'] ?: 'Track your order' }}</a></p>
                                @break
                            @case('Order Summary')
                                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:8px 0 16px;font-size:13px;border-top:1px solid #E7E3D8;">
                                    <tr><td colspan="2" style="padding:12px 0 6px;color:#8A8D90;">Order {{ $order->order_number }} · {{ $order->created_at->format('j M Y') }}</td></tr>
                                    @foreach ($order->items as $item)
                                        <tr><td style="padding:4px 0;">{{ $item->qty }}× {{ $item->name }}{{ $item->frame_size ? ' · Size '.$item->frame_size : '' }}</td><td align="right" style="padding:4px 0;">{{ $money($item->unit_price * $item->qty) }}</td></tr>
                                    @endforeach
                                    <tr><td style="padding:10px 0 0;border-top:1px solid #E7E3D8;font-weight:bold;">Total</td><td align="right" style="padding:10px 0 0;border-top:1px solid #E7E3D8;font-weight:bold;">{{ $money($order->total) }}</td></tr>
                                    <tr><td colspan="2" style="padding:4px 0 0;color:#8A8D90;">{{ $order->paymentLabel() }} · {{ $order->isPaid() ? 'Paid' : 'Payment due' }}</td></tr>
                                </table>
                                @if ($order->hasShippingAddress())
                                    <p style="margin:0 0 16px;font-size:13px;line-height:1.5;color:#4A4D50;"><strong style="color:#0F1113;">Delivering to</strong><br>{!! collect($order->shippingAddressLines())->map(fn ($l) => e($l))->implode('<br>') !!}</p>
                                @endif
                                @break
                            @case('Divider')
                                <hr style="border:none;border-top:1px solid #E7E3D8;margin:16px 0;">
                                @break
                            @case('Footer')
                                <p style="margin:16px 0 0;padding-top:12px;border-top:1px solid #E7E3D8;font-size:11px;color:#8A8D90;">{{ $block['text'] }}</p>
                                @break
                        @endswitch
                    @endforeach

                    @if ($order->status === 'Shipped' && $order->tracking_number)
                        <p style="margin:0 0 16px;font-size:13px;">Courier: <strong>{{ $order->carrier }}</strong> · Tracking number: <strong>{{ $order->tracking_number }}</strong></p>
                    @endif
                    @if ($order->delivery_from && ! in_array($order->status, ['Delivered', 'Cancelled'], true))
                        <p style="margin:0 0 16px;font-size:13px;">Estimated delivery: <strong>{{ $order->delivery_from->format('j M') }} – {{ $order->delivery_to->format('j M Y') }}</strong></p>
                    @endif
                    @unless ($hasCta)
                        <p style="margin:20px 0;"><a href="{{ $trackUrl }}" style="color:#0F1113;font-size:14px;">View your order status →</a></p>
                    @endunless
                </td></tr>
                <tr><td style="padding:16px 28px 24px;font-size:11px;color:#8A8D90;">
                    {{ $storeName }} · Questions? Reply to this email or contact {{ \App\Support\Settings::get('concierge_email', config('mail.from.address')) }}.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
