<x-layouts.app title="Create your account">
    <div class="grid min-h-[80vh] place-items-center px-4 py-12">
        <div class="w-full max-w-md rounded-3xl border border-border bg-card p-8 text-center">
            <x-site.logo-mark class="mx-auto h-12 w-12" />
            <h1 class="mt-4 text-2xl font-semibold">Create your atelier account</h1>
            <p class="mt-1 text-sm text-muted-foreground">Save prescriptions, track lens surfacing and reorder in one tap.</p>
            <form method="POST" action="{{ route('register') }}" class="mt-8 space-y-3 text-left">
                @csrf
                @foreach ([['name', 'Full name', 'text', 'name'], ['email', 'Email', 'email', 'email'], ['password', 'Password (min 8 characters)', 'password', 'new-password'], ['password_confirmation', 'Confirm password', 'password', 'new-password']] as [$field, $label, $type, $auto])
                    <label class="block text-sm">{{ $label }}
                        <input type="{{ $type }}" name="{{ $field }}" @if ($type !== 'password') value="{{ old($field) }}" @else minlength="8" @endif required autocomplete="{{ $auto }}" class="mt-1 w-full rounded-xl border border-input bg-secondary px-4 py-3 text-sm">
                        @error($field)<span data-error role="alert" class="mt-1 block text-xs text-destructive">{{ $message }}</span>@enderror
                    </label>
                @endforeach
                <button class="w-full rounded-xl bg-primary py-3.5 text-sm font-medium text-primary-foreground">Create account</button>
            </form>
            <a href="{{ route('login') }}" class="mt-5 inline-block text-xs text-muted-foreground underline">Already have an account? Sign in</a>
        </div>
    </div>
</x-layouts.app>
