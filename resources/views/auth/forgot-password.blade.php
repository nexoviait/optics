<x-layouts.app title="Reset your password">
    <div class="grid min-h-[80vh] place-items-center px-4 py-12">
        <div class="w-full max-w-md rounded-3xl border border-border bg-card p-8 text-center">
            <x-site.logo-mark class="mx-auto h-12 w-12" />
            <h1 class="mt-4 text-2xl font-semibold">Forgot your password?</h1>
            <p class="mt-1 text-sm text-muted-foreground">Enter your account email and we'll send you a link to choose a new one.</p>
            @if (session('status'))
                <p role="status" class="mt-6 rounded-xl bg-bronze-soft p-3 text-left text-xs">{{ session('status') }}</p>
            @endif
            <form method="POST" action="{{ route('password.email') }}" class="mt-8 space-y-3 text-left">
                @csrf
                <label class="block text-sm">Email<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email" class="mt-1 w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm">@error('email')<span class="mt-1 block text-xs text-destructive">{{ $message }}</span>@enderror</label>
                <button class="w-full rounded-xl bg-primary py-3.5 text-sm font-medium text-primary-foreground">Email reset link</button>
            </form>
            <a href="{{ route('login') }}" class="mt-5 inline-block text-xs text-muted-foreground underline">Back to sign in</a>
        </div>
    </div>
</x-layouts.app>
