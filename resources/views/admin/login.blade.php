<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <x-site.favicon />
    <title>Admin Portal | {{ $store['store_name'] ?? 'REFRA' }}</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500&family=Manrope:wght@400;500;600;700&family=Montserrat:wght@400;600;700&display=swap">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-background px-4">
    <form method="POST" action="{{ route('admin.login') }}" x-data="{ show: false }" class="w-full max-w-md rounded-3xl border border-border bg-card p-10 text-center shadow-[0_30px_60px_-40px_var(--obsidian)]">
        @csrf
        <x-site.logo-mark class="mx-auto h-14 w-14" />
        <h1 class="mt-4 font-display text-2xl font-bold tracking-[0.35em]">{{ strtoupper(strtok($store['store_name'] ?? 'REFRA', ' ')) }}</h1>
        <span class="eyebrow mt-4 inline-block rounded-full bg-secondary px-3 py-1 text-[0.6rem]">Admin portal</span>
        <p class="mt-3 text-sm text-muted-foreground">Sign in to access the management console.</p>
        @if (session('status'))<p class="mt-6 rounded-xl bg-secondary px-4 py-3 text-xs" role="status">{{ session('status') }}</p>@endif
        <div class="mt-8 space-y-4 text-left">
            <label class="block text-sm font-medium">Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" maxlength="255" class="mt-1.5 w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm"></label>
            <label class="block text-sm font-medium">Password
                <div class="relative mt-1.5">
                    <input :type="show ? 'text' : 'password'" type="password" name="password" required autocomplete="current-password" class="w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm">
                    <button type="button" @click="show = !show" class="absolute right-3 top-3 text-muted-foreground" aria-label="Toggle password"><x-lucide-eye x-show="!show" class="h-4 w-4" /><x-lucide-eye-off x-show="show" class="h-4 w-4" /></button>
                </div>
            </label>
            @error('email')<p class="text-xs text-destructive">{{ $message }}</p>@enderror
            <button class="w-full rounded-xl bg-primary py-3.5 text-sm font-medium text-primary-foreground">Sign in</button>
            <p class="text-center text-[0.65rem] text-muted-foreground">Only accounts with the administrator role can enter.</p>
        </div>
        <p class="mt-8 text-xs text-muted-foreground">© {{ date('Y') }} {{ $store['store_name'] ?? 'REFRA' }}</p>
    </form>
</body>
</html>
