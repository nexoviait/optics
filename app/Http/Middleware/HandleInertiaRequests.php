<?php

namespace App\Http\Middleware;

use App\Support\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia pages render inside the regular storefront layout (header, footer,
 * cart and search stay Blade + Alpine), so only page-level data is shared here.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'inertia';

    public function share(Request $request): array
    {
        $store = Settings::all();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $request->user() ? ['name' => $request->user()->name] : null,
            ],
            'store' => [
                'name' => $store['store_name'] ?? 'REFRA',
                'exchangeDays' => (int) ($store['exchange_days'] ?? 14),
                'warrantyDays' => (int) ($store['warranty_days'] ?? 365),
                'rxSubmissionDays' => (int) ($store['rx_submission_days'] ?? 15),
                'supportPhone' => $store['support_phone'] ?? null,
            ],
            'urls' => [
                'home' => route('home'),
                'cart' => route('cart.index'),
                'login' => route('login'),
                'deliveryEstimate' => route('delivery.estimate'),
                'visionMatch' => route('visionmatch'),
                'sizeGuidePage' => url('/pages/frame-size-guide'),
                'mediapipe' => asset('vendor/mediapipe'),
                'tryOnPortrait' => asset('images/face.jpg'),
            ],
            'translations' => [
                'gallery' => trans('gallery'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ]);
    }
}
