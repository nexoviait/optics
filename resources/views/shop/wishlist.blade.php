<x-layouts.app title="My Wishlist">
    <section class="mx-auto max-w-7xl px-5 py-12">
        <x-site.section-head level="1" eyebrow="Saved for later" title="My Wishlist" :sub="$products->count().' '.Str::plural('frame', $products->count()).' saved'" />
        @guest
            <p class="mt-4 rounded-lg bg-bronze-soft p-3 text-xs">Your wishlist is saved in this browser. <a href="{{ route('login') }}" class="font-semibold underline">Sign in</a> to keep it on your account across devices.</p>
        @endguest
        @if ($products->isEmpty())
            <div class="mt-10 rounded-2xl border border-border bg-card p-12 text-center">
                <x-lucide-heart class="mx-auto h-10 w-10 text-muted-foreground" />
                <h2 class="mt-4 text-xl font-medium">Your wishlist is empty</h2>
                <p class="mt-2 text-sm text-muted-foreground">Tap the heart on any frame to save it here.</p>
                <a href="{{ route('eyeglasses') }}" class="btn mt-6 px-5 py-3">Explore frames</a>
            </div>
        @else
            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($products as $product)
                    <x-site.product-card :product="$product" />
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
