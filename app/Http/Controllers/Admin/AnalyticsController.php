<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LensOption;
use App\Models\LensSku;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Models\VisionSession;
use App\Services\SalesReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnalyticsController extends Controller
{
    public function index(Request $request, SalesReport $report): View
    {
        $view = in_array($request->query('view'), ['daily', 'weekly'], true) ? $request->query('view') : 'daily';
        $daily = $report->daily();
        $summary = $report->summary();

        $liveItems = fn () => OrderItem::query()->whereHas('order', fn ($q) => $q->where('status', '!=', 'Cancelled'));

        $topFrames = $liveItems()->whereNotNull('product_id')
            ->select('name', DB::raw('SUM(qty) as units'), DB::raw('SUM(unit_price * qty) as revenue'))
            ->groupBy('name')->orderByDesc('revenue')->take(8)->get();

        $lensLabels = LensOption::query()->pluck('label', 'code');
        $topLenses = $liveItems()->whereNotNull('lens')
            ->select('lens', DB::raw('SUM(qty) as units'))
            ->groupBy('lens')->orderByDesc('units')->get()
            ->map(fn ($row) => ['name' => $lensLabels[$row->lens] ?? $row->lens, 'units' => (int) $row->units]);

        $customers = User::query()->customers()->withCount(['orders' => fn ($q) => $q->where('status', '!=', 'Cancelled')])->get();
        $buyers = $customers->where('orders_count', '>', 0);
        $repeat = $buyers->where('orders_count', '>', 1)->count();

        $refunded = (float) Payment::query()->where('status', 'Refunded')->sum('amount');
        $gross = (float) Order::query()->where('status', '!=', 'Cancelled')->sum('total');

        $vmTotal = VisionSession::query()->count();
        $statusCounts = Order::query()->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $active = max(1, $statusCounts->except('Cancelled')->sum());

        return view('admin.analytics', [
            'view' => $view,
            'series' => $report->bucket($daily, $view === 'weekly' ? 7 : 1),
            'summary' => $summary,
            'net' => $daily->sum('net'),
            'refundRate' => $gross > 0 ? round($refunded / $gross * 100, 1) : 0,
            'reconciliation' => [
                'gross' => $summary['sales'] + $summary['discounts'],
                'discounts' => $summary['discounts'],
                'refunds' => (float) Payment::query()->where('status', 'Refunded')->where('refunded_at', '>=', now()->subDays(30))->sum('amount'),
                'fees' => $daily->sum('sales') - $daily->sum('net'),
            ],
            'customerMix' => [
                'buyers' => $buyers->count(),
                'repeat_rate' => $buyers->count() ? round($repeat / $buyers->count() * 100, 1) : 0,
                'avg_ltv' => $buyers->count() ? (float) Order::query()->whereNotNull('user_id')->where('status', '!=', 'Cancelled')->sum('total') / $buyers->count() : 0,
            ],
            'visionmatch' => [
                'sessions' => $vmTotal,
                'conversion' => $vmTotal ? round(VisionSession::query()->where('converted', true)->count() / $vmTotal * 100, 1) : 0,
            ],
            'fulfilment' => [
                'Delivered & shipped' => round((($statusCounts['Delivered'] ?? 0) + ($statusCounts['Shipped'] ?? 0)) / $active * 100),
                'Lab processing' => round((($statusCounts['Processing'] ?? 0) + ($statusCounts['Ready to Ship'] ?? 0)) / $active * 100),
                'Awaiting verification' => round(($statusCounts['New'] ?? 0) / $active * 100),
            ],
            'topFrames' => $topFrames,
            'topLenses' => $topLenses,
            'lowFrames' => Product::query()->lowStock()->count(),
            'lowLenses' => LensSku::query()->lowStock()->count(),
        ]);
    }

    public function export(string $report, SalesReport $sales): StreamedResponse
    {
        abort_unless(in_array($report, ['sales', 'orders', 'payments'], true), 404);

        return response()->streamDownload(function () use ($report, $sales) {
            $out = fopen('php://output', 'w');

            match ($report) {
                'sales' => (function () use ($out, $sales) {
                    fputcsv($out, ['date', 'gross', 'net', 'orders', 'aov']);
                    foreach ($sales->daily() as $d) {
                        fputcsv($out, [$d['date'], $d['sales'], $d['net'], $d['orders'], $d['aov']]);
                    }
                })(),
                'orders' => (function () use ($out) {
                    fputcsv($out, ['order', 'date', 'customer', 'email', 'status', 'channel', 'payment', 'subtotal', 'discounts', 'total']);
                    Order::query()->with('user')->latest()->chunk(200, function ($orders) use ($out) {
                        foreach ($orders as $o) {
                            fputcsv($out, [$o->order_number, $o->created_at->toDateTimeString(), self::csvText($o->customerName()), self::csvText($o->contactEmail()), $o->status, $o->channel, $o->payment_method, $o->subtotal, (float) $o->member_discount + (float) $o->gift_discount, $o->total]);
                        }
                    });
                })(),
                'payments' => (function () use ($out) {
                    fputcsv($out, ['reference', 'order', 'date', 'method', 'status', 'amount', 'fee', 'net']);
                    Payment::query()->with('order')->latest()->chunk(200, function ($payments) use ($out) {
                        foreach ($payments as $p) {
                            fputcsv($out, [$p->reference, $p->order?->order_number, $p->created_at->toDateTimeString(), $p->method, $p->status, $p->amount, $p->fee, $p->netPayout()]);
                        }
                    });
                })(),
            };

            fclose($out);
        }, "refra-{$report}-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Stop spreadsheet apps from running customer-supplied text as a formula. */
    private static function csvText(?string $value): ?string
    {
        return $value !== null && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
