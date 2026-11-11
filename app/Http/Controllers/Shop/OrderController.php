<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderFulfilment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** A signed-in customer's own orders: details, tracking and cancellation. */
class OrderController extends Controller
{
    public function show(Request $request, Order $order): View
    {
        $this->authorizeOwner($request, $order);

        return view('shop.orders.show', [
            'order' => $order->load('items.product', 'items.prescription', 'payment', 'events'),
            'owner' => true,
        ]);
    }

    public function cancel(Request $request, Order $order, OrderFulfilment $fulfilment): RedirectResponse
    {
        $this->authorizeOwner($request, $order);

        if (! $order->isCancellableByCustomer()) {
            return back()->withErrors(['status' => 'This order is already being prepared and can no longer be cancelled online. Please contact our concierge.']);
        }

        $fulfilment->transition($order, 'Cancelled', ['note' => 'Cancelled by customer'], $request->user());

        return redirect()->route('orders.show', $order)->with('success', "Order {$order->order_number} has been cancelled.");
    }

    private function authorizeOwner(Request $request, Order $order): void
    {
        // 404 rather than 403 so order numbers can't be probed.
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
