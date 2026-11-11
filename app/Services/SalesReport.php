<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Aggregates order data for the admin dashboard and analytics. Grouping is
 * done in PHP so the queries stay portable across MySQL and SQLite.
 */
class SalesReport
{
    /** @return Collection<int, array{day: string, date: string, sales: float, net: float, orders: int, aov: float}> */
    public function daily(int $days = 30): Collection
    {
        $start = CarbonImmutable::today()->subDays($days - 1);

        $orders = Order::query()
            ->where('status', '!=', 'Cancelled')
            ->where('created_at', '>=', $start)
            ->get(['total', 'gift_discount', 'member_discount', 'created_at'])
            ->groupBy(fn (Order $o) => $o->created_at->toDateString());

        return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $orders) {
            $date = $start->addDays($offset);
            $group = $orders->get($date->toDateString(), collect());
            $sales = (float) $group->sum('total');
            $count = $group->count();
            // Net = revenue after card processing fees.
            $net = $group->sum(fn (Order $o) => (float) $o->total - Payment::feeFor((float) $o->total));

            return [
                'day' => $date->format('M j'),
                'date' => $date->toDateString(),
                'sales' => round($sales, 2),
                'net' => round($net, 2),
                'orders' => $count,
                'aov' => $count ? round($sales / $count, 2) : 0,
            ];
        });
    }

    /** Re-bucket a daily series into blocks of N days. */
    public function bucket(Collection $daily, int $size): Collection
    {
        if ($size <= 1) {
            return $daily;
        }

        return $daily->chunk($size)->map(function (Collection $chunk) {
            $sales = $chunk->sum('sales');
            $orders = $chunk->sum('orders');

            return [
                'day' => $chunk->first()['day'].' – '.$chunk->last()['day'],
                'sales' => round($sales, 2),
                'net' => round($chunk->sum('net'), 2),
                'orders' => $orders,
                'aov' => $orders ? round($sales / $orders, 2) : 0,
            ];
        })->values();
    }

    /** Totals for a window, plus the change against the preceding window of equal length. */
    public function summary(int $days = 30): array
    {
        $current = $this->window(now()->subDays($days), now());
        $previous = $this->window(now()->subDays($days * 2), now()->subDays($days));

        $delta = fn (float $now, float $before) => $before > 0 ? round(($now - $before) / $before * 100, 1) : null;

        return [
            'sales' => $current['sales'],
            'orders' => $current['orders'],
            'aov' => $current['aov'],
            'discounts' => $current['discounts'],
            'sales_delta' => $delta($current['sales'], $previous['sales']),
            'orders_delta' => $delta($current['orders'], $previous['orders']),
            'aov_delta' => $delta($current['aov'], $previous['aov']),
        ];
    }

    private function window($from, $to): array
    {
        $query = Order::query()->where('status', '!=', 'Cancelled')->whereBetween('created_at', [$from, $to]);
        $sales = (float) (clone $query)->sum('total');
        $orders = (clone $query)->count();

        return [
            'sales' => $sales,
            'orders' => $orders,
            'aov' => $orders ? $sales / $orders : 0.0,
            'discounts' => (float) (clone $query)->sum('member_discount') + (float) (clone $query)->sum('gift_discount'),
        ];
    }
}
