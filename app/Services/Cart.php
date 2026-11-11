<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\LensOption;
use App\Models\PowerType;
use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Session-backed shopping bag. Only references (product id, options) are
 * stored; prices are recalculated from the catalog on every read.
 */
class Cart
{
    private const ITEMS = 'cart.items';

    private const COUPON = 'cart.coupon';

    public function __construct(private Session $session, private Pricing $pricing) {}

    /** @param array{product_id: int, finish?: ?string, size?: ?string, lens?: ?array} $item */
    public function add(array $item, int $qty = 1): string
    {
        $key = Str::uuid()->toString();
        $items = $this->raw();
        $items[$key] = [
            'key' => $key,
            'product_id' => $item['product_id'],
            'qty' => max(1, min(10, $qty)),
            'finish' => $item['finish'] ?? null,
            'size' => $item['size'] ?? null,
            'lens' => $item['lens'] ?? null,
        ];
        $this->session->put(self::ITEMS, $items);

        return $key;
    }

    public function updateQty(string $key, int $qty): void
    {
        $items = $this->raw();
        if (isset($items[$key])) {
            $items[$key]['qty'] = max(1, min(10, $qty));
            $this->session->put(self::ITEMS, $items);
        }
    }

    public function remove(string $key): void
    {
        $items = $this->raw();
        unset($items[$key]);
        $this->session->put(self::ITEMS, $items);
    }

    public function clear(): void
    {
        $this->session->forget([self::ITEMS, self::COUPON]);
    }

    public function count(): int
    {
        return (int) collect($this->raw())->sum('qty');
    }

    /**
     * Bag lines hydrated with their product and current unit price. Lines whose
     * product is no longer purchasable are dropped.
     *
     * @return Collection<int, array>
     */
    public function items(): Collection
    {
        $raw = $this->raw();
        $products = Product::query()->active()->whereIn('id', array_column($raw, 'product_id'))->get()->keyBy('id');
        $powerTypes = PowerType::query()->pluck('label', 'code');
        $lensOptions = LensOption::query()->pluck('label', 'code');

        return collect($raw)
            ->filter(fn (array $item) => $products->has($item['product_id']))
            ->map(function (array $item) use ($products, $powerTypes, $lensOptions) {
                $product = $products[$item['product_id']];
                $item['product'] = $product;
                $item['unit_price'] = (float) $product->price + $this->pricing->lensPrice($item['lens']);
                $item['line_total'] = $item['unit_price'] * $item['qty'];
                $item['power_label'] = $item['lens'] ? ($powerTypes[$item['lens']['power_type']] ?? null) : null;
                $item['lens_label'] = $item['lens']['lens'] ?? null ? ($lensOptions[$item['lens']['lens']] ?? null) : null;

                return $item;
            })
            ->values();
    }

    public function applyCoupon(?string $code): ?Coupon
    {
        $coupon = Coupon::findValid($code);
        $coupon ? $this->session->put(self::COUPON, $coupon->code) : $this->session->forget(self::COUPON);

        return $coupon;
    }

    public function coupon(): ?Coupon
    {
        return Coupon::findValid($this->session->get(self::COUPON));
    }

    public function bill(?Collection $items = null): array
    {
        $items ??= $this->items();

        return $this->pricing->computeBill(
            $items->map(fn (array $i) => ['price' => $i['unit_price'], 'qty' => $i['qty']])->all(),
            $this->coupon(),
        );
    }

    private function raw(): array
    {
        return $this->session->get(self::ITEMS, []);
    }
}
