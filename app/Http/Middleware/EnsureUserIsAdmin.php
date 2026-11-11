<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public const LAST_ACTIVITY_KEY = 'admin.last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('admin.login'));
        }

        if (! $request->user()->isAdmin()) {
            return response()->view('admin.no-access', [], 403);
        }

        // Admin sessions expire after a period of inactivity, independent of
        // the (longer) storefront session lifetime.
        $timeout = (int) config('store.admin_idle_minutes') * 60;
        $last = (int) $request->session()->get(self::LAST_ACTIVITY_KEY, time());

        if ($timeout > 0 && time() - $last > $timeout) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->guest(route('admin.login'))->with('status', 'Your admin session expired after inactivity. Please sign in again.');
        }

        $request->session()->put(self::LAST_ACTIVITY_KEY, time());

        $response = $next($request);

        // Admin pages hold customer and order data: never let browsers or proxies cache them.
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
