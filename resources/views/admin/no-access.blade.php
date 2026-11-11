<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>No admin access</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center bg-background px-4 text-center">
    <div class="max-w-sm">
        <x-site.logo-mark class="mx-auto h-12 w-12" />
        <h1 class="mt-4 text-xl font-semibold">No admin access</h1>
        <p class="mt-2 text-sm text-muted-foreground">{{ auth()->user()?->email }} is signed in but isn't an administrator.</p>
        <div class="mt-6 flex justify-center gap-2">
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn">Sign out</button></form>
            <a href="{{ route('home') }}" class="btn-ghost">Back to store</a>
        </div>
    </div>
</body>
</html>
