<x-layouts.app title="Track your order" description="Check the status of your order with your order number and email or phone.">
    <div class="grid min-h-[70vh] place-items-center px-4 py-12">
        <div class="w-full max-w-md rounded-3xl border border-border bg-card p-8">
            <x-lucide-package-search class="h-10 w-10 text-muted-foreground" />
            <h1 class="mt-4 text-2xl font-semibold">Track your order</h1>
            <p class="mt-1 text-sm text-muted-foreground">Enter your order number and the email or phone number you used at checkout.</p>
            <form method="POST" action="{{ route('orders.track.lookup') }}" class="mt-6 space-y-3">
                @csrf
                <x-admin.field label="Order number" name="order_number"><input name="order_number" value="{{ old('order_number') }}" required maxlength="30" placeholder="e.g. RF-48321" autocomplete="off" class="input font-mono uppercase"></x-admin.field>
                <x-admin.field label="Email or phone number" name="contact"><input name="contact" value="{{ old('contact', auth()->user()?->email) }}" required maxlength="255" autocomplete="email" placeholder="name@example.com or +880 1712 345678" class="input"></x-admin.field>
                <button class="btn w-full py-3">Find my order</button>
            </form>
            @auth<p class="mt-5 text-center text-xs text-muted-foreground">Signed in? See every order in <a href="{{ route('account') }}" class="underline">My account</a>.</p>@endauth
        </div>
    </div>
</x-layouts.app>
