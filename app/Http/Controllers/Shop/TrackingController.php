<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Order tracking without signing in: look up by order number + account
 * email, or open the signed link sent in every order email.
 */
class TrackingController extends Controller
{
    public function form(): View
    {
        return view('shop.orders.track');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'order_number' => ['required', 'string', 'max:30'],
            'contact' => ['required', 'string', 'max:255'],
        ], [], ['contact' => 'email or phone number']);

        $order = Order::query()->where('order_number', strtoupper(trim($data['order_number'])))->first();

        // One message for "no such order" and "details don't match" so lookups can't enumerate orders.
        if (! $order || ! $this->contactMatches($order, $data['contact'])) {
            return back()->withInput()->withErrors(['order_number' => 'We couldn’t find an order matching those details. Check your order number and the email or phone you used at checkout.']);
        }

        if ($order->user_id !== null && $request->user()?->id === $order->user_id) {
            return redirect()->route('orders.show', $order);
        }

        return redirect()->to($order->publicTrackingUrl());
    }

    /** The checkout email (case-insensitive) or delivery phone (digits only, at least 7) must match. */
    private function contactMatches(Order $order, string $contact): bool
    {
        $contact = trim($contact);

        if (str_contains($contact, '@')) {
            return $order->contactEmail() !== null && strcasecmp($order->contactEmail(), $contact) === 0;
        }

        $digits = preg_replace('/\D/', '', $contact);
        $phone = preg_replace('/\D/', '', (string) $order->ship_phone);

        return strlen($digits) >= 7 && $phone !== '' && hash_equals($phone, $digits);
    }

    public function show(Order $order): View
    {
        return view('shop.orders.show', [
            'order' => $order->load('items.product', 'payment', 'events'),
            'owner' => false,
        ]);
    }
}
