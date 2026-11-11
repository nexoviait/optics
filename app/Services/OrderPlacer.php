<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The only writer of orders. Items are re-priced from the catalog inside a
 * transaction so clients can never set totals, and stock is reserved.
 */
class OrderPlacer
{
    private const SHIPPING_FIELDS = [
        'email', 'ship_name', 'ship_phone', 'ship_line1', 'ship_line2', 'ship_city', 'ship_postal', 'ship_country',
        'customer_note', 'delivery_from', 'delivery_to',
    ];

    public function __construct(private Pricing $pricing, private Shipping $shipping) {}

    /**
     * @param  list<array{product_id: int, qty: int, finish?: ?string, size?: ?string, lens?: ?array}>  $lines
     * @param  ?User  $customer  null for guest checkout (contact email in $shipping is then optional)
     * @param  array<string, ?string>  $shipping  contact email, ship_* address fields and customer_note
     * @param  array{payer_account?: ?string, payer_reference?: ?string}  $payment  mobile banking details to verify
     */
    public function place(?User $customer, array $lines, string $paymentMethod, ?string $couponCode, string $channel, array $shipping = [], array $payment = []): Order
    {
        if ($lines === []) {
            throw ValidationException::withMessages(['items' => 'Your bag is empty.']);
        }

        $estimate = isset($shipping['ship_postal'], $shipping['ship_country'])
            ? $this->shipping->estimate($shipping['ship_postal'], $shipping['ship_country'])
            : null;
        if (($estimate['status'] ?? null) === 'available') {
            $shipping += ['delivery_from' => $estimate['earliest_date'], 'delivery_to' => $estimate['latest_date']];
        }

        return DB::transaction(function () use ($customer, $lines, $paymentMethod, $couponCode, $channel, $shipping, $payment) {
            $products = Product::query()
                ->active()
                ->whereIn('id', array_column($lines, 'product_id'))
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $priced = [];
            $demand = [];
            foreach ($lines as $line) {
                $product = $products[$line['product_id']] ?? null;
                if (! $product) {
                    throw ValidationException::withMessages(['items' => 'A frame in your bag is no longer available.']);
                }

                $lens = $this->pricing->normaliseLens($line['lens'] ?? null);
                $demand[$product->id] = ($demand[$product->id] ?? 0) + $line['qty'];
                $priced[] = $line + [
                    'product' => $product,
                    'lens_config' => $lens,
                    'unit' => (float) $product->price + $this->pricing->lensPrice($lens),
                ];
            }

            foreach ($demand as $productId => $qty) {
                if ($products[$productId]->stock < $qty) {
                    throw ValidationException::withMessages(['items' => "Only {$products[$productId]->stock} × {$products[$productId]->name} left in stock."]);
                }
            }

            $coupon = Coupon::findValid($couponCode);
            $bill = $this->pricing->computeBill(
                array_map(fn (array $p) => ['price' => $p['unit'], 'qty' => $p['qty']], $priced),
                $coupon,
            );

            $order = Order::create([
                'user_id' => $customer?->id,
                'status' => 'New',
                'subtotal' => $bill['subtotal'],
                'member_discount' => $bill['member_discount'],
                'coupon_code' => $coupon?->code,
                'gift_discount' => $bill['gift_discount'],
                'total' => $bill['total'],
                'payment_method' => $paymentMethod,
                'channel' => $channel,
            ] + Arr::only($shipping, self::SHIPPING_FIELDS) + ['email' => $customer?->email]);

            foreach ($priced as $line) {
                $lens = $line['lens_config'];
                $prescription = $this->storePrescription($customer, $lens['rx_mode'] ?? null, $line['lens'] ?? []);

                $order->items()->create([
                    'product_id' => $line['product']->id,
                    'name' => $line['product']->name,
                    'finish' => $line['finish'] ?? null,
                    'frame_size' => $line['size'] ?? null,
                    'unit_price' => $line['unit'],
                    'qty' => $line['qty'],
                    'power_type' => $lens['power_type'] ?? null,
                    'lens' => $lens['lens'] ?? null,
                    'rx_mode' => $lens['rx_mode'] ?? null,
                    'prescription_id' => $prescription?->id,
                ]);
            }

            foreach ($demand as $productId => $qty) {
                $products[$productId]->decrement('stock', $qty);
            }

            Payment::create([
                'order_id' => $order->id,
                'method' => $paymentMethod,
                'amount' => $bill['total'],
                'fee' => Payment::feeFor($bill['total']),
                'status' => PaymentMethod::statusFor($paymentMethod),
            ] + array_intersect_key($payment, array_flip(['payer_account', 'payer_reference'])));

            return $order->refresh();
        });
    }

    private function storePrescription(?User $customer, ?string $mode, array $lens): ?Prescription
    {
        if (! in_array($mode, ['manual', 'upload'], true)) {
            return null;
        }

        $rx = $lens['rx'] ?? [];

        return Prescription::query()->create([
            'user_id' => $customer?->id,
            'source' => $mode,
            'patient_name' => $rx['name'] ?? null,
            'phone' => $rx['phone'] ?? null,
            'r_sph' => $rx['r_sph'] ?? null,
            'r_cyl' => $rx['r_cyl'] ?? null,
            'r_axis' => $rx['r_axis'] ?? null,
            'l_sph' => $rx['l_sph'] ?? null,
            'l_cyl' => $rx['l_cyl'] ?? null,
            'l_axis' => $rx['l_axis'] ?? null,
            'add_power' => $rx['add_power'] ?? null,
            'pd' => $rx['pd'] ?? null,
            'file_path' => $lens['file_path'] ?? null,
            'file_name' => $lens['file_name'] ?? null,
        ]);
    }
}
