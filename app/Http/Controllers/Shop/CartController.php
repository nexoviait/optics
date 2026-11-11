<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\User;
use App\Models\VisionSession;
use App\Services\Cart;
use App\Services\OrderFulfilment;
use App\Services\OrderPlacer;
use App\Services\Pricing;
use App\Services\Shipping;
use App\Support\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(private Cart $cart) {}

    public function index(Request $request, Shipping $shipping): View
    {
        $items = $this->cart->items();
        $user = $request->user();

        // Signing in from the bag should bring the shopper straight back here.
        if (! $user && $items->isNotEmpty()) {
            redirect()->setIntendedUrl(route('cart.index'));
        }

        return view('shop.cart', [
            'items' => $items,
            'bill' => $this->cart->bill($items),
            'coupon' => $this->cart->coupon(),
            'paymentMethods' => PaymentMethod::query()->active()->get(),
            'freeIndex' => $items->sum('qty') >= 2 && Settings::bool('member_bogo_enabled', true)
                ? $items->sortBy('unit_price')->keys()->first()
                : null,
            'countries' => $shipping->shippableCountries(),
            'address' => $this->savedAddress($user),
        ]);
    }

    /** Prefill delivery details from the customer's most recent shipped-to address. */
    private function savedAddress(?User $user): array
    {
        if (! $user) {
            return [];
        }

        $last = $user->orders()->whereNotNull('ship_line1')->latest('id')->first();

        return [
            'ship_name' => $last->ship_name ?? $user->name,
            'ship_phone' => $last->ship_phone ?? $user->phone,
            'ship_line1' => $last?->ship_line1,
            'ship_line2' => $last?->ship_line2,
            'ship_city' => $last->ship_city ?? $user->city,
            'ship_postal' => $last?->ship_postal,
            'ship_country' => $last?->ship_country,
        ];
    }

    public function store(AddToCartRequest $request, Pricing $pricing): JsonResponse|RedirectResponse
    {
        $data = $request->validated();
        $lens = $pricing->normaliseLens($data['lens'] ?? null);

        if ($lens) {
            if ($lens['rx_mode'] === 'manual') {
                $lens['rx'] = $data['lens']['rx'] ?? [];
            }
            if ($lens['rx_mode'] === 'upload' && $request->hasFile('prescription_file')) {
                $file = $request->file('prescription_file');
                $lens['file_path'] = $file->store('prescriptions', 'local');
                $lens['file_name'] = mb_substr($file->getClientOriginalName(), 0, 200);
            }
        }

        $product = Product::query()->active()->findOrFail($data['product_id']);
        $this->cart->add([
            'product_id' => $product->id,
            'finish' => $data['finish'] ?? $product->color,
            'size' => $data['size'] ?? null,
            'lens' => $lens,
        ], $data['qty'] ?? 1);

        if ($sessionId = $request->session()->get('visionmatch.session')) {
            VisionSession::query()->whereKey($sessionId)->update(['converted' => true]);
        }

        $message = $lens ? "{$product->name} configured and added to bag" : "{$product->name} added to bag";

        if ($request->expectsJson()) {
            return response()->json(['message' => $message, 'count' => $this->cart->count(), 'redirect' => $lens ? route('cart.index') : null]);
        }

        return redirect()->route('cart.index')->with('success', $message);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        $request->validate(['qty' => ['required', 'integer', 'min:1', 'max:10']]);
        $this->cart->updateQty($key, (int) $request->input('qty'));

        return back();
    }

    public function destroy(string $key): RedirectResponse
    {
        $this->cart->remove($key);

        return back()->with('success', 'Item removed from your bag.');
    }

    public function coupon(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['nullable', 'string', 'max:40']]);
        $code = $request->input('code');

        if (! $code) {
            $this->cart->applyCoupon(null);

            return back()->with('success', 'Code removed.');
        }

        $coupon = $this->cart->applyCoupon($code);

        return $coupon
            ? back()->with('success', "{$coupon->code} applied — {$coupon->label()}.")
            : back()->withErrors(['code' => "That code isn't valid."])->withInput();
    }

    public function checkout(CheckoutRequest $request, OrderPlacer $placer, OrderFulfilment $fulfilment): RedirectResponse
    {
        $lines = $this->cart->items()->map(fn (array $item) => [
            'product_id' => $item['product_id'],
            'qty' => $item['qty'],
            'finish' => $item['finish'],
            'size' => $item['size'],
            'lens' => $item['lens'],
        ])->all();
        $shipping = $request->shipping();

        // Account creation and order placement succeed or fail together.
        [$order, $customer] = DB::transaction(function () use ($request, $placer, $lines, $shipping) {
            $customer = $request->user() ?? ($request->wantsAccount() ? $this->createAccount($request, $shipping) : null);

            $order = $placer->place(
                $customer,
                $lines,
                $request->validated('payment_method'),
                $this->cart->coupon()?->code,
                'web',
                $shipping,
                $request->payerDetails(),
            );

            return [$order, $customer];
        });

        if ($customer) {
            $this->rememberContactDetails($customer, $shipping);
            if (! $request->user()) {
                Auth::login($customer);
                $request->session()->regenerate();
            }
        }

        $this->cart->clear();
        // Lets a guest open their confirmation page in this browser session only.
        $request->session()->push('checkout.orders', $order->id);
        $fulfilment->placed($order, $customer);

        return redirect()->route('cart.confirmed', $order);
    }

    public function confirmed(Request $request, Order $order): View
    {
        $mine = $order->user_id !== null && $order->user_id === $request->user()?->id;
        abort_unless($mine || in_array($order->id, $request->session()->get('checkout.orders', []), true), 404);

        return view('shop.order-confirmed', ['order' => $order->load('items.product', 'payment')]);
    }

    /** @param array<string, ?string> $shipping */
    private function createAccount(CheckoutRequest $request, array $shipping): User
    {
        return User::query()->create([
            'name' => $shipping['ship_name'],
            'email' => $shipping['email'],
            'password' => $request->validated('password'),
        ]);
    }

    /**
     * Save checkout details to the profile where it has none yet, so the
     * next checkout is prefilled. Existing profile values are never overwritten.
     *
     * @param  array<string, ?string>  $shipping
     */
    private function rememberContactDetails(User $customer, array $shipping): void
    {
        $customer->fill(array_filter([
            'phone' => $customer->phone ?: $shipping['ship_phone'],
            'city' => $customer->city ?: $shipping['ship_city'],
        ]))->save();
    }
}
