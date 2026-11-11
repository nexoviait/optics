<?php

namespace App\Services;

use App\Models\ShippingZone;
use Carbon\CarbonImmutable;

/**
 * Delivery estimates from configured shipping zones. Explicit result states
 * distinguish invalid postal input from missing coverage so the storefront
 * never fabricates dates.
 */
class Shipping
{
    /** @param list<string> $holidays */
    public function businessDate(CarbonImmutable $start, int $days, array $holidays = []): CarbonImmutable
    {
        $date = $start;
        while ($days > 0) {
            $date = $date->addDay();
            if (! $date->isWeekend() && ! in_array($date->toDateString(), $holidays, true)) {
                $days--;
            }
        }

        return $date;
    }

    /**
     * Countries covered by an enabled shipping zone — the only ones checkout accepts.
     *
     * @return array<string, string> code => name
     */
    public function shippableCountries(): array
    {
        $covered = ShippingZone::query()->where('enabled', true)->pluck('countries')->flatten()->unique()->all();

        return collect(config('store.delivery_regions'))
            ->only($covered)
            ->except('OTHER')
            ->map(fn (array $region) => $region['name'])
            ->all();
    }

    public function estimate(string $postal, string $region, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $regions = config('store.delivery_regions');
        $normalized = strtoupper(trim($postal));

        if (! isset($regions[$region])) {
            return ['status' => 'invalid', 'message' => 'Choose a delivery country.'];
        }

        $zone = ShippingZone::query()
            ->where('enabled', true)
            ->get()
            ->first(fn (ShippingZone $z) => in_array($region, $z->countries ?? [], true));

        if (! $zone) {
            return ['status' => 'unavailable', 'message' => 'Delivery estimate unavailable: no active shipping zone is configured for this destination.'];
        }

        if (! preg_match($regions[$region]['pattern'], $normalized)) {
            return ['status' => 'invalid', 'message' => "Enter a valid postal code for {$regions[$region]['name']}."];
        }

        if ($zone->transit_min < 1 || $zone->transit_max < $zone->transit_min) {
            return ['status' => 'unavailable', 'message' => 'Delivery estimate unavailable: shipping timings are not configured.'];
        }

        $holidays = $zone->holidays ?? [];
        $dispatch = $this->businessDate($now, (int) config('store.dispatch_business_days'), $holidays);
        $earliest = $this->businessDate($dispatch, $zone->transit_min, $holidays);
        $latest = $this->businessDate($dispatch, $zone->transit_max, $holidays);

        return [
            'status' => 'available',
            'postal' => $normalized,
            'dispatch' => $dispatch->format('j M'),
            'earliest' => $earliest->format('j M'),
            'latest' => $latest->format('j M'),
            'earliest_date' => $earliest->toDateString(),
            'latest_date' => $latest->toDateString(),
            'courier' => $zone->courier,
            'zone' => $zone->name,
            'transit_min' => $zone->transit_min,
            'transit_max' => $zone->transit_max,
            'dispatch_days' => (int) config('store.dispatch_business_days'),
        ];
    }
}
