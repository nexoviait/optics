<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LensOption;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderFulfilment;
use App\Services\OrderPlacer;
use App\Services\Pricing;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), config('store.order_statuses'), true) ? $request->query('status') : null;
        $search = trim((string) $request->query('q'));

        return view('admin.orders.index', [
            'orders' => Order::query()->with(['user', 'items'])->latest()
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('order_number', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")->orWhere('ship_name', 'like', "%{$search}%")->orWhere('ship_phone', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"))))
                ->paginate(25)->withQueryString(),
            'counts' => Order::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status'),
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', [
            'order' => $order->load(['user', 'items.prescription', 'payment', 'events.user']),
            'lensLabels' => LensOption::query()->pluck('label', 'code'),
        ]);
    }

    public function updateStatus(Request $request, Order $order, OrderFulfilment $fulfilment): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(config('store.order_statuses'))],
            'carrier' => ['nullable', 'required_with:tracking_number', Rule::in(array_keys(config('store.carriers')))],
            'tracking_number' => [
                Rule::requiredIf($request->input('status') === 'Shipped' && ! $order->tracking_number),
                'nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9 -]+$/',
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['tracking_number.required' => 'Add the courier tracking number before marking the order as shipped.']);

        $order = $fulfilment->transition($order, $data['status'], $data, $request->user());
        ActivityLog::record("Order {$order->order_number} moved to {$data['status']}", $order);

        return back()->with('success', "Order moved to {$data['status']}.");
    }

    public function create(Pricing $pricing): View
    {
        return view('admin.orders.create', [
            'customers' => User::query()->customers()->orderBy('name')->get(['id', 'name', 'email']),
            'products' => Product::query()->active()->ordered()->get(['id', 'name', 'price', 'stock']),
            'lensOptions' => LensOption::query()->active()->get(['code', 'label', 'price']),
            'memberBogo' => Settings::bool('member_bogo_enabled', true),
        ]);
    }

    public function store(Request $request, OrderPlacer $placer, OrderFulfilment $fulfilment): RedirectResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', Rule::exists('users', 'id')->where('role', User::ROLE_CUSTOMER)],
            'payment_method' => ['required', Rule::in(['phone', 'link', 'cod', 'boutique'])],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.lens' => ['required', 'string', 'exists:lens_options,code'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:10'],
        ], ['customer_id.required' => 'Select a customer.']);

        $order = $placer->place(
            User::query()->findOrFail($data['customer_id']),
            array_map(fn (array $i) => [
                'product_id' => (int) $i['product_id'],
                'qty' => (int) $i['qty'],
                'lens' => ['power_type' => 'single', 'lens' => $i['lens'], 'rx_mode' => 'later'],
            ], $data['items']),
            $data['payment_method'],
            null,
            'concierge',
        );
        $fulfilment->placed($order, $request->user());
        ActivityLog::record("Manual order {$order->order_number} created", $order);

        return redirect()->route('admin.orders.show', $order)->with('success', "Order {$order->order_number} created.");
    }
}
