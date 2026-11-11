<x-layouts.app title="My Account">
    <div class="mx-auto max-w-6xl px-5 py-12">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div><p class="eyebrow text-muted-foreground">{{ $user->tier }}</p><h1 class="mt-1 text-3xl font-medium">My Atelier Account</h1><p class="text-sm text-muted-foreground">{{ $user->email }}</p></div>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn-ghost">Sign out</button></form>
        </div>
        <div class="mt-10 grid gap-6 lg:grid-cols-[1.5fr_1fr]">
            <section class="panel p-6">
                <h2 class="font-semibold">Orders</h2>
                @forelse ($orders as $order)
                    <a href="{{ route('orders.show', $order) }}" class="mt-4 block rounded-xl border border-border p-4 transition hover:border-primary">
                        <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-mono text-sm">{{ $order->order_number }}</span><x-admin.status :s="$order->status" /></div>
                        <p class="mt-1 text-xs text-muted-foreground">{{ $order->created_at->format('j M Y') }} · ${{ number_format((float) $order->total, 2) }} · {{ $order->paymentLabel() }}</p>
                        <ul class="mt-2 text-sm">
                            @foreach ($order->items as $item)
                                <li>{{ $item->qty }}× {{ $item->name }}{{ $item->frame_size ? ' · Size '.$item->frame_size : '' }}{{ $item->rx_mode === 'later' ? ' · Rx pending' : '' }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-3 flex items-center justify-between text-xs">
                            <span class="text-muted-foreground">
                                @if ($order->tracking_number){{ $order->carrier }} · {{ $order->tracking_number }}
                                @elseif ($order->delivery_from && ! in_array($order->status, ['Delivered', 'Cancelled'], true))Arrives {{ $order->delivery_from->format('j M') }} – {{ $order->delivery_to->format('j M') }}
                                @else{{ $order->statusLabel() }}@endif
                            </span>
                            <span class="font-medium">Track & details →</span>
                        </p>
                    </a>
                @empty
                    <p class="mt-4 text-sm text-muted-foreground">No orders yet. <a href="{{ route('eyeglasses') }}" class="underline">Browse frames</a></p>
                @endforelse
            </section>
            <div class="space-y-6">
                <section class="panel p-6">
                    <h2 class="font-semibold">Profile</h2>
                    <form method="POST" action="{{ route('account.update') }}" class="mt-4 space-y-3">
                        @csrf @method('PUT')
                        <x-admin.field label="Full name" name="name"><input name="name" value="{{ old('name', $user->name) }}" maxlength="80" required class="input"></x-admin.field>
                        <x-admin.field label="Phone" name="phone"><input name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" class="input"></x-admin.field>
                        <x-admin.field label="City" name="city"><input name="city" value="{{ old('city', $user->city) }}" maxlength="80" class="input"></x-admin.field>
                        <button class="btn w-full py-2.5">Save profile</button>
                    </form>
                </section>
                <section class="panel p-6">
                    <h2 class="font-semibold">Password</h2>
                    <form method="POST" action="{{ route('password.update') }}" class="mt-4 space-y-3">
                        @csrf @method('PUT')
                        <x-admin.field label="Current password">
                            <input type="password" name="current_password" required autocomplete="current-password" class="input">
                            @error('current_password', 'password')<span data-error role="alert" class="mt-1 block text-[0.7rem] text-destructive">{{ $message }}</span>@enderror
                        </x-admin.field>
                        <x-admin.field label="New password">
                            <input type="password" name="password" required autocomplete="new-password" class="input">
                            @error('password', 'password')<span data-error role="alert" class="mt-1 block text-[0.7rem] text-destructive">{{ $message }}</span>@enderror
                        </x-admin.field>
                        <x-admin.field label="Confirm new password"><input type="password" name="password_confirmation" required autocomplete="new-password" class="input"></x-admin.field>
                        <button class="btn w-full py-2.5">Update password</button>
                    </form>
                </section>
                <section class="panel p-6">
                    <h2 class="font-semibold">Saved prescriptions</h2>
                    @forelse ($prescriptions as $rx)
                        <div class="mt-3 rounded-lg bg-secondary p-3 text-xs">
                            <div class="flex justify-between"><span class="font-semibold">{{ $rx->patient_name ?? ($rx->source === 'upload' ? 'Uploaded prescription' : 'Prescription') }}</span><span class="text-muted-foreground">{{ $rx->created_at->format('j M Y') }}</span></div>
                            <p class="mt-1 {{ $rx->source === 'upload' ? '' : 'font-mono' }}">{{ $rx->summary() }}</p>
                        </div>
                    @empty
                        <p class="mt-4 text-sm text-muted-foreground">Prescriptions you enter at checkout are saved here.</p>
                    @endforelse
                </section>
            </div>
        </div>
    </div>
</x-layouts.app>
