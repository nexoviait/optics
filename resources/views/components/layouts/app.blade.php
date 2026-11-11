<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="cart-url" content="{{ route('cart.index') }}">
    <title @if ($inertia ?? false) data-inertia="" @endif>{{ isset($title) ? $title.' | '.($store['store_name'] ?? 'REFRA') : ($store['meta_title'] ?? 'REFRA Atelier Optics') }}</title>
    <meta name="description" content="{{ $description ?? ($store['meta_description'] ?? '') }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?? ($store['meta_title'] ?? 'REFRA') }}">
    <meta property="og:description" content="{{ $description ?? ($store['meta_description'] ?? '') }}">
    <meta name="twitter:card" content="summary_large_image">
    <x-site.favicon />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=JetBrains+Mono:wght@400;500&family=Manrope:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{ $head ?? '' }}
</head>
<body class="min-h-screen" data-cart-count="{{ app(\App\Services\Cart::class)->count() }}" data-wishlist-count="{{ app(\App\Services\Wishlist::class)->count() }}">
    <x-site.header />
    <main>
        {{ $slot }}
    </main>
    <x-site.footer />
    <x-toasts />
</body>
</html>
