@php
    $nav = [
        ['admin.dashboard', 'Overview', 'layout-dashboard', 'admin.dashboard'],
        ['admin.products.index', 'Products & Inventory', 'package', 'admin.products.*'],
        ['admin.lenses.index', 'Optical Lenses', 'circle', 'admin.lenses.*'],
        ['admin.orders.index', 'Orders', 'shopping-cart', 'admin.orders.*'],
        ['admin.customers.index', 'Customers', 'users', 'admin.customers.*'],
        ['admin.payments.index', 'Payments', 'credit-card', 'admin.payments.*'],
        ['admin.requests.index', 'Requests & Leads', 'inbox', 'admin.requests.*'],
        ['admin.visionmatch.index', 'REFRA VisionMatch', 'scan-face', 'admin.visionmatch.*'],
        ['admin.content.index', 'Website Content', 'panels-top-left', 'admin.content.*'],
        ['admin.pages.index', 'Content Pages', 'file-text', 'admin.pages.*'],
        ['admin.email.index', 'Email & Notifications', 'mail', 'admin.email.*'],
        ['admin.analytics.index', 'Analytics', 'chart-column', 'admin.analytics.*'],
        ['admin.settings.index', 'Settings', 'settings', 'admin.settings.*'],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex">
    <title>{{ ($title ?? 'Admin') }} | {{ $store['store_name'] ?? 'REFRA' }} Admin</title>
    <x-site.favicon />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Manrope:wght@400;500;600;700&family=Montserrat:wght@400;500;600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-background" x-data="{ mobile: false, alerts: false, tab: 'all' }">
    <div class="flex min-h-screen">
        <aside :class="mobile ? 'translate-x-0' : '-translate-x-full'"
               class="fixed inset-y-0 left-0 z-40 flex w-60 shrink-0 flex-col overflow-y-auto border-r border-border bg-card p-4 transition-transform lg:sticky lg:top-0 lg:h-screen lg:translate-x-0">
            <a href="{{ route('home') }}" class="flex items-center gap-2 px-2"><x-site.logo-mark /><span class="font-display font-semibold tracking-[0.3em]">{{ strtoupper(strtok($store['store_name'] ?? 'REFRA', ' ')) }}</span></a>
            <p class="eyebrow mt-1 px-2 text-[0.5rem] text-muted-foreground">Admin console</p>
            <nav class="mt-6 space-y-0.5">
                @foreach ($nav as [$route, $label, $icon, $pattern])
                    <a href="{{ route($route) }}" @class([
                        'flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm',
                        'bg-primary text-primary-foreground' => request()->routeIs($pattern),
                        'text-muted-foreground hover:bg-secondary hover:text-foreground' => ! request()->routeIs($pattern),
                    ])>@svg('lucide-'.$icon, 'h-4 w-4'){{ $label }}</a>
                @endforeach
            </nav>
            <a href="{{ route('admin.profile.edit') }}" @class(['mt-auto flex items-center gap-2 rounded-md px-2.5 py-2 text-sm', 'bg-primary text-primary-foreground' => request()->routeIs('admin.profile.*'), 'text-muted-foreground hover:text-foreground' => ! request()->routeIs('admin.profile.*')])><x-lucide-shield-check class="h-4 w-4" />My account</a>
            <form method="POST" action="{{ route('logout') }}" class="pt-2">
                @csrf
                <input type="hidden" name="admin" value="1">
                <button class="flex w-full items-center gap-2 px-2.5 text-sm text-muted-foreground hover:text-foreground"><x-lucide-log-out class="h-4 w-4" />Sign out</button>
            </form>
        </aside>
        <div x-show="mobile" x-cloak class="fixed inset-0 z-30 bg-obsidian/40 lg:hidden" @click="mobile = false"></div>

        <div class="min-w-0 flex-1">
            <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-border bg-background/90 px-5 backdrop-blur">
                <button class="lg:hidden" @click="mobile = true" aria-label="Menu"><x-lucide-menu class="h-5 w-5" /></button>
                <span class="eyebrow text-[0.55rem] text-muted-foreground">● {{ count($alerts) ? count($alerts).' item'.(count($alerts) > 1 ? 's' : '').' need attention' : 'All systems nominal' }}</span>
                <a href="{{ route('home') }}" class="ml-auto text-xs text-muted-foreground hover:text-foreground">View store ↗</a>
                <button @click="alerts = true" class="relative rounded-full border border-border p-2" aria-label="Alerts">
                    <x-lucide-bell class="h-4 w-4" />
                    @if (count($alerts))<span class="absolute -right-1 -top-1 grid h-4 min-w-4 place-items-center rounded-full bg-bronze px-1 text-[0.55rem] font-bold text-obsidian">{{ count($alerts) }}</span>@endif
                </button>
                <a href="{{ route('admin.profile.edit') }}" class="grid h-8 w-8 place-items-center rounded-full bg-primary text-xs text-primary-foreground" title="{{ auth()->user()->email }}">{{ auth()->user()->initials() ?: 'AD' }}</a>
            </header>
            <div class="space-y-6 p-5 lg:p-8">
                {{ $slot }}
            </div>
        </div>
    </div>

    {{-- Alerts drawer --}}
    <div x-show="alerts" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="alerts = false">
        <div class="absolute inset-0 bg-obsidian/40" @click="alerts = false"></div>
        <aside x-show="alerts" x-transition:enter="transition duration-200" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               class="absolute inset-y-0 right-0 w-full overflow-y-auto bg-background p-5 sm:max-w-md">
            <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold">Atelier Alerts</h2><button @click="alerts = false" aria-label="Close"><x-lucide-x class="h-4 w-4" /></button></div>
            <div class="mt-4 flex flex-wrap gap-1">
                @foreach (['all' => 'All', 'orders' => 'Orders & Rx', 'inventory' => 'Inventory', 'requests' => 'Requests'] as $key => $label)
                    <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'bg-primary text-primary-foreground' : 'bg-secondary'" class="rounded-full px-3 py-1 text-xs">{{ $label }}</button>
                @endforeach
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($alerts as $alert)
                    <div x-show="tab === 'all' || tab === '{{ $alert['category'] }}'" class="rounded-xl border border-border bg-card p-4">
                        <div class="flex justify-between gap-2"><span class="eyebrow text-[0.5rem] text-muted-foreground">{{ $alert['category'] }}</span><span class="text-[0.65rem] text-muted-foreground">{{ $alert['time'] }}</span></div>
                        <h3 class="mt-1 text-sm font-semibold">{{ $alert['title'] }}</h3>
                        <p class="mt-1 text-xs text-muted-foreground">{{ $alert['detail'] }}</p>
                        <a href="{{ $alert['url'] }}" class="btn btn-sm mt-3">{{ $alert['action'] }}</a>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">No alerts. Everything is running smoothly.</p>
                @endforelse
            </div>
        </aside>
    </div>

    <x-toasts />
</body>
</html>
