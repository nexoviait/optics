<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), config('store.payment_statuses'), true) ? $request->query('status') : null;
        $search = trim((string) $request->query('q'));
        $date = $request->query('date');

        $sum = fn (string $s) => (float) Payment::query()->where('status', $s)->sum('amount');

        return view('admin.payments.index', [
            'payments' => Payment::query()->with('order.user')->latest()
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($date, fn ($q) => $q->whereDate('created_at', $date))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('reference', 'like', "%{$search}%")->orWhere('payer_reference', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('ship_name', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")))))
                ->paginate(25)->withQueryString(),
            'kpis' => [
                'succeeded' => $sum('Succeeded'),
                'pending' => $sum('Pending'),
                'refunded' => $sum('Refunded'),
                'failed' => Payment::query()->where('status', 'Failed')->count(),
            ],
            'methods' => PaymentMethod::query()->orderBy('sort_order')->orderBy('id')->get(),
            'status' => $status,
            'search' => $search,
            'date' => $date,
        ]);
    }

    /** Staff confirm offline / mobile banking payments once the money has arrived. */
    public function confirm(Payment $payment): RedirectResponse
    {
        $confirmed = Payment::query()->whereKey($payment->getKey())->where('status', 'Pending')->update(['status' => 'Succeeded']);

        if (! $confirmed) {
            return back()->withErrors(['payment' => 'Only pending payments can be confirmed.']);
        }

        ActivityLog::record("Payment confirmed for {$payment->reference}", $payment);

        return back()->with('success', 'Payment '.$payment->reference.' marked as received.');
    }

    public function refund(Payment $payment): RedirectResponse
    {
        // Conditional update: only one of several concurrent requests can flip the status.
        $refunded = Payment::query()->whereKey($payment->getKey())->where('status', 'Succeeded')
            ->update(['status' => 'Refunded', 'refunded_at' => now()]);

        if (! $refunded) {
            return back()->withErrors(['payment' => 'Only succeeded payments can be refunded.']);
        }

        ActivityLog::record("Refund issued for {$payment->reference}", $payment);

        return back()->with('success', 'Refund of $'.number_format((float) $payment->amount, 2).' recorded.');
    }
}
