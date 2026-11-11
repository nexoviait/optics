<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\LensOption;
use App\Models\PowerType;
use App\Support\Settings;
use Illuminate\Validation\ValidationException;

/**
 * Pure pricing rules shared by the storefront, checkout and admin manual orders.
 * Prices always come from the database, never from the client.
 */
class Pricing
{
    /**
     * Validate a lens configuration and return its normalised form.
     *
     * @param  array{power_type?: string, lens?: ?string, rx_mode?: ?string}|null  $lens
     * @return array{power_type: string, lens: ?string, rx_mode: ?string}|null
     */
    public function normaliseLens(?array $lens): ?array
    {
        if (! $lens || empty($lens['power_type'])) {
            return null;
        }

        $power = PowerType::query()->active()->where('code', $lens['power_type'])->first();
        if (! $power) {
            throw ValidationException::withMessages(['lens' => 'Choose a valid power type.']);
        }

        $lensCode = null;
        if ($power->needs_lens) {
            $lensCode = $lens['lens'] ?? null;
            if (! $lensCode || ! LensOption::query()->active()->where('code', $lensCode)->exists()) {
                throw ValidationException::withMessages(['lens' => 'Choose a lens for this power type.']);
            }
        }

        $rxMode = null;
        if ($power->needs_rx) {
            $rxMode = $lens['rx_mode'] ?? 'later';
            if (! in_array($rxMode, config('store.rx_modes'), true)) {
                throw ValidationException::withMessages(['lens' => 'Choose how you will provide your prescription.']);
            }
        }

        return ['power_type' => $power->code, 'lens' => $lensCode, 'rx_mode' => $rxMode];
    }

    /** Surcharge for a normalised lens configuration. */
    public function lensPrice(?array $lens): float
    {
        if (! $lens) {
            return 0.0;
        }

        $power = PowerType::query()->where('code', $lens['power_type'])->first();
        $price = (float) ($power?->price ?? 0);

        if ($power?->needs_lens && ! empty($lens['lens'])) {
            $price += (float) (LensOption::query()->where('code', $lens['lens'])->value('price') ?? 0);
        }

        return $price;
    }

    /**
     * Member 1+1 privilege makes the cheapest unit free in a bag of 2+ units;
     * an atelier coupon then applies to the remainder.
     *
     * @param  list<array{price: float, qty: int}>  $lines
     */
    public function computeBill(array $lines, ?Coupon $coupon = null): array
    {
        $subtotal = 0.0;
        $units = [];
        foreach ($lines as $line) {
            $subtotal += $line['price'] * $line['qty'];
            array_push($units, ...array_fill(0, max(0, $line['qty']), (float) $line['price']));
        }

        $memberDiscount = Settings::bool('member_bogo_enabled', true) && count($units) >= 2 ? min($units) : 0.0;
        $afterMember = $subtotal - $memberDiscount;
        $giftDiscount = $coupon ? $coupon->discountFor($afterMember) : 0.0;
        $total = max(0, $afterMember - $giftDiscount);

        return [
            'subtotal' => round($subtotal, 2),
            'member_discount' => round($memberDiscount, 2),
            'gift_discount' => round($giftDiscount, 2),
            'total' => round($total, 2),
            'installment' => round($total / 4, 2),
            'coupon' => $coupon?->code,
        ];
    }
}
