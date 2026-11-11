<?php

namespace App\Providers;

use App\Models\LensSku;
use App\Models\Order;
use App\Models\Page;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Services\Cart;
use App\Services\Wishlist;
use App\Support\Settings;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(Cart::class);
        $this->app->scoped(Wishlist::class);
    }

    public function boot(): void
    {
        Paginator::defaultView('components.pagination');

        if ($proxies = config('store.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        Password::defaults(fn () => $this->app->isProduction()
            ? Password::min(10)->letters()->mixedCase()->numbers()->uncompromised()
            : Password::min(8)->letters()->numbers());

        View::composer(['components.layouts.*', 'components.site.*', 'shop.*', 'admin.*', 'auth.*', 'account.*'], function ($view) {
            $view->with('store', Settings::all());
        });

        View::composer('components.site.header', function ($view) {
            $view->with('cartCount', app(Cart::class)->count());
            $view->with('wishlistCount', app(Wishlist::class)->count());
        });

        View::composer('components.site.product-card', function ($view) {
            $view->with('wishlistIds', app(Wishlist::class)->ids());
        });

        View::composer('components.site.footer', function ($view) {
            $view->with('footerPages', Page::query()->published()->whereNotNull('footer_group')
                ->orderBy('sort_order')->get(['slug', 'title', 'footer_group'])->groupBy('footer_group'));
        });

        View::composer('components.layouts.admin', function ($view) {
            $view->with('alerts', $this->adminAlerts());
        });
    }

    /** Operational alerts derived from live data for the admin alerts drawer. */
    private function adminAlerts(): array
    {
        $alerts = [];

        Order::query()->where('status', 'New')->latest()->take(5)->get()->each(function (Order $order) use (&$alerts) {
            $alerts[] = [
                'category' => 'orders',
                'title' => 'New order awaiting Rx verification',
                'detail' => "Order {$order->order_number} · $".number_format((float) $order->total, 2).' via '.$order->channel.'.',
                'action' => 'Review order',
                'url' => route('admin.orders.show', $order),
                'time' => $order->created_at->diffForHumans(),
            ];
        });

        Product::query()->lowStock()->get()->each(function (Product $product) use (&$alerts) {
            $alerts[] = [
                'category' => 'inventory',
                'title' => "Low frame stock — {$product->name}",
                'detail' => "Only {$product->stock} units left (threshold {$product->low_stock_threshold}).",
                'action' => 'Restock',
                'url' => route('admin.products.edit', $product),
                'time' => $product->updated_at->diffForHumans(),
            ];
        });

        LensSku::query()->lowStock()->get()->each(function (LensSku $sku) use (&$alerts) {
            $alerts[] = [
                'category' => 'inventory',
                'title' => "Optical blank depletion — {$sku->name}",
                'detail' => "{$sku->stock} blanks left (< {$sku->low_stock_threshold} threshold).",
                'action' => 'Manage lenses',
                'url' => route('admin.lenses.index'),
                'time' => $sku->updated_at->diffForHumans(),
            ];
        });

        ServiceRequest::query()->where('status', 'New')->latest()->take(5)->get()->each(function (ServiceRequest $request) use (&$alerts) {
            $alerts[] = [
                'category' => 'requests',
                'title' => $request->typeLabel().' requested',
                'detail' => trim(($request->name ?? 'Guest').' · '.($request->preferred_date?->format('j M Y') ?? $request->notes)),
                'action' => 'Open requests',
                'url' => route('admin.requests.index'),
                'time' => $request->created_at->diffForHumans(),
            ];
        });

        return $alerts;
    }
}
