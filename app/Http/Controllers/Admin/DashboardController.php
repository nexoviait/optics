<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Services\SalesReport;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(SalesReport $report): View
    {
        return view('admin.dashboard', [
            'summary' => $report->summary(),
            'trend' => $report->daily(),
            'recentOrders' => Order::query()->with('user')->latest()->take(8)->get(),
            'liveOrderCount' => Order::query()->count(),
            'stock' => Product::query()->orderBy('stock')->get(),
            'lowStockCount' => Product::query()->lowStock()->count(),
        ]);
    }
}
