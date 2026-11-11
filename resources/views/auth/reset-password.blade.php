<x-layouts.app title="Choose a new password">
    <div class="grid min-h-[80vh] place-items-center px-4 py-12">
        <div class="w-full max-w-md rounded-3xl border border-border bg-card p-8 text-center">
            <x-site.logo-mark class="mx-auto h-12 w-12" />
            <h1 class="mt-4 text-2xl font-semibold">Choose a new password</h1>
            <p class="mt-1 text-sm text-muted-foreground">Use letters and numbers. You'll sign in with it afterwards.</p>
            <form method="POST" action="{{ route('password.store') }}" class="mt-8 space-y-3 text-left">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <label class="block text-sm">Email<input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email" class="mt-1 w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm">@error('email')<span class="mt-1 block text-xs text-destructive">{{ $message }}</span>@enderror</label>
                <label class="block text-sm">New password<input type="password" name="password" required autofocus autocomplete="new-password" class="mt-1 w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm">@error('password')<span class="mt-1 block text-xs text-destructive">{{ $message }}</span>@enderror</label>
                <label class="block text-sm">Confirm new password<input type="password" name="password_confirmation" required autocomplete="new-password" class="mt-1 w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm"></label>
                <button class="w-full rounded-xl bg-primary py-3.5 text-sm font-medium text-primary-foreground">Reset password</button>
            </form>
        </div>
    </div>
</x-layouts.app>
