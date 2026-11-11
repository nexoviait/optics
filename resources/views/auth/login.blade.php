<x-layouts.app title="Sign in">
    <div class="grid min-h-[80vh] place-items-center px-4 py-12">
        <div class="w-full max-w-md rounded-3xl border border-border bg-card p-8 text-center">
            <x-site.logo-mark class="mx-auto h-12 w-12" />
            <h1 class="mt-4 text-2xl font-semibold">Welcome back</h1>
            <p class="mt-1 text-sm text-muted-foreground">Save prescriptions, track lens surfacing and reorder in one tap.</p>
            @if (session('status'))
                <p role="status" class="mt-6 rounded-xl bg-bronze-soft p-3 text-left text-xs">{{ session('status') }}</p>
            @endif
            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-3 text-left">
                @csrf
                <label class="block text-sm">Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-1 w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm">@error('email')<span data-error role="alert" class="mt-1 block text-xs text-destructive">{{ $message }}</span>@enderror</label>
                <label class="block text-sm">Password<input type="password" name="password" required autocomplete="current-password" class="mt-1 w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm"></label>
                <div class="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                    <label class="flex items-center gap-2"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
                    <a href="{{ route('password.request') }}" class="underline">Forgot password?</a>
                </div>
                <button class="w-full rounded-xl bg-primary py-3.5 text-sm font-medium text-primary-foreground">Sign in</button>
            </form>
            <a href="{{ route('register') }}" class="mt-5 inline-block text-xs text-muted-foreground underline">New to {{ strtok($store['store_name'] ?? 'REFRA', ' ') }}? Create an account</a>
        </div>
    </div>
</x-layouts.app>
