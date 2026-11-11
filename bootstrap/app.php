<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
        // Without this, a changed or reset password leaves sessions on other devices signed in.
        $middleware->authenticateSessions();
        $middleware->web(append: [SecurityHeaders::class, HandleInertiaRequests::class]);
        $middleware->redirectGuestsTo(fn ($request) => $request->is('admin*') ? route('admin.login') : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Card details are only checked at checkout, never stored: never flash them into the session.
        $exceptions->dontFlash(['card_number', 'card_expiry', 'card_cvc']);
    })->create();
