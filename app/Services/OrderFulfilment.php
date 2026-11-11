<?php

namespace App\Services;

use App\Mail\OrderUpdate;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The only place an order's status changes. Each change is applied under a
 * row lock, written to the order's history and emailed to the customer.
 */
class OrderFulfilment
{
    /** Log the initial status of a newly placed order and send the confirmation. */
    public function placed(Order $order, ?User $actor = null): void
    {
        $order->events()->create(['status' => $order->status, 'note' => 'Order placed', 'user_id' => $actor?->id]);
        $this->notify($order);
    }

    /**
     * @param  array{note?: ?string, carrier?: ?string, tracking_number?: ?string}  $details
     *
     * @throws ValidationException
     */
    public function transition(Order $order, string $status, array $details = [], ?User $actor = null): Order
    {
        [$order, $changed, $previous] = DB::transaction(function () use ($order, $status, $details, $actor) {
            $current = Order::query()->with('items.product', 'payment')->lockForUpdate()->findOrFail($order->getKey());
            $previous = $current->status;

            $this->guard($current, $status);

            if (filled($details['tracking_number'] ?? null)) {
                $current->forceFill(['carrier' => $details['carrier'] ?? $current->carrier, 'tracking_number' => trim($details['tracking_number'])]);
            }

            if ($previous === $status) {
                $current->save();

                return [$current, false, $previous];
            }

            match ($status) {
                'Cancelled' => $this->cancel($current),
                'Shipped' => $current->shipped_at ??= now(),
                'Delivered' => $current->delivered_at ??= now(),
                default => null,
            };

            $current->forceFill(['status' => $status])->save();
            $current->events()->create(['status' => $status, 'note' => $details['note'] ?? null, 'user_id' => $actor?->id]);

            return [$current, true, $previous];
        });

        if ($changed && $this->isProgress($previous, $status)) {
            $this->notify($order);
        }

        return $order;
    }

    /** Email the customer the update for the order's current status; mail failures never block fulfilment. */
    public function notify(Order $order): void
    {
        $order->loadMissing('user', 'items', 'payment');
        $email = $order->contactEmail();
        if (! $email) {
            return;
        }

        try {
            Mail::to($email, $order->customerName())->send(new OrderUpdate($order));
        } catch (Throwable $e) {
            Log::error('Order email failed', ['order' => $order->order_number, 'status' => $order->status, 'error' => $e->getMessage()]);
        }
    }

    /** @throws ValidationException */
    private function guard(Order $order, string $status): void
    {
        $error = match (true) {
            $order->status === 'Cancelled' && $status !== 'Cancelled' => 'Cancelled orders cannot be reopened — their stock has been released. Create a new order instead.',
            $order->status === 'Delivered' && $status === 'Cancelled' => 'Delivered orders cannot be cancelled. Arrange a return or remake instead.',
            default => null,
        };

        if ($error) {
            throw ValidationException::withMessages(['status' => $error]);
        }
    }

    /** Release reserved stock and settle the payment record. */
    private function cancel(Order $order): void
    {
        foreach ($order->items as $item) {
            $item->product?->increment('stock', $item->qty);
        }

        $order->cancelled_at = now();

        match ($order->payment?->status) {
            'Succeeded' => $order->payment->update(['status' => 'Refunded', 'refunded_at' => now()]),
            'Pending' => $order->payment->update(['status' => 'Failed']),
            default => null,
        };
    }

    /** Only forward moves and cancellations are announced; admin corrections stay quiet. */
    private function isProgress(string $from, string $to): bool
    {
        $pipeline = config('store.order_pipeline');

        return $to === 'Cancelled' || array_search($to, $pipeline, true) > array_search($from, $pipeline, true);
    }
}
