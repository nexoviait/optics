@php
    $steps = [1 => 'Bag', 2 => 'Delivery', 3 => 'Payment'];
    $deliveryFields = ['email', 'password', 'create_account', 'ship_name', 'ship_phone', 'ship_line1', 'ship_line2', 'ship_city', 'ship_postal', 'ship_country', 'customer_note'];
    $paymentFields = ['payment_method', 'card_name', 'card_number', 'card_expiry', 'card_cvc', 'items'];
    $startStep = $errors->hasAny($deliveryFields) ? 2 : ($errors->hasAny($paymentFields) ? 3 : 1);
    $addr = fn (string $key) => old($key, $address[$key] ?? null);
    $country = $addr('ship_country') ?: (count($countries) === 1 ? array_key_first($countries) : '');
@endphp
<x-layouts.app title="Shopping Bag & Checkout" description="Review your configured frames, add delivery details and check out securely.">
    @if ($items->isEmpty())
        <div class="mx-auto max-w-lg px-5 py-24 text-center">
            <x-lucide-shopping-bag class="mx-auto h-10 w-10 text-muted-foreground" />
            <h1 class="mt-6 text-3xl font-medium">Your bag is empty</h1>
            <p class="mt-2 text-sm text-muted-foreground">Explore our flagships or let VisionMatch™ find your perfect frame.</p>
            <div class="mt-8 flex justify-center gap-3"><a href="{{ route('eyeglasses') }}" class="btn px-5 py-3">Shop frames</a><a href="{{ route('visionmatch') }}" class="btn-ghost px-5 py-3">Find my frames</a></div>
            <p class="mt-10 text-xs text-muted-foreground">Already ordered? <a href="{{ route('orders.track') }}" class="underline">Track your order</a></p>
        </div>
    @else
        <div x-data="checkout(@js([
            'step' => $startStep,
            'payment' => old('payment_method', $paymentMethods->first()?->code),
            'country' => $country,
            'postal' => (string) $addr('ship_postal'),
            'estimateUrl' => route('delivery.estimate'),
        ]))">
            {{-- Progress --}}
            <nav aria-label="Checkout progress" class="border-b border-border bg-card">
                <ol class="mx-auto flex max-w-7xl flex-wrap items-center gap-x-4 gap-y-2 px-5 py-3 text-sm">
                    @foreach ($steps as $n => $label)
                        <li class="flex items-center gap-2">
                            <button type="button" @click="{{ $n }} < step && go({{ $n }})" :disabled="{{ $n }} > step" :aria-current="step === {{ $n }} ? 'step' : null"
                                    :class="step === {{ $n }} ? 'font-semibold' : 'text-muted-foreground'" class="flex items-center gap-2 disabled:cursor-default">
                                <span :class="step > {{ $n }} ? 'bg-sage-soft' : (step === {{ $n }} ? 'bg-primary text-primary-foreground' : 'bg-muted')" class="grid h-5 w-5 place-items-center rounded-full text-[0.65rem]">
                                    <span x-show="step <= {{ $n }}">{{ $n }}</span><x-lucide-check x-show="step > {{ $n }}" x-cloak class="h-3 w-3" />
                                </span>
                                {{ $n === 1 ? "Bag ({$items->sum('qty')})" : $label }}
                            </button>
                            @if ($n < count($steps))<span class="text-border" aria-hidden="true">/</span>@endif
                        </li>
                    @endforeach
                    <li class="eyebrow ml-auto hidden text-[0.55rem] text-muted-foreground lg:block"><x-lucide-lock class="mr-1 inline h-3 w-3" />Secure checkout · <x-lucide-truck class="mx-1 inline h-3 w-3" />Insured delivery</li>
                </ol>
            </nav>

            <div class="mx-auto grid max-w-7xl gap-8 px-5 py-10 lg:grid-cols-[1.4fr_1fr]">
                <div>
                    {{-- Step 1: Bag --}}
                    <section x-show="step === 1">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-border pb-4">
                            <h1 class="text-2xl font-semibold">Shopping Bag <span class="text-base font-normal text-muted-foreground">({{ $items->sum('qty') }} {{ Str::plural('item', $items->sum('qty')) }})</span></h1>
                            @if ($bill['total'] >= (float) ($store['free_shipping_threshold'] ?? 0))<span class="eyebrow text-[0.55rem] text-muted-foreground">Complimentary express delivery</span>@endif
                        </div>
                        <div class="mt-6 space-y-5">
                            @foreach ($items as $index => $item)
                                @include('shop.partials.bag-item', ['item' => $item, 'index' => $index, 'free' => $freeIndex === $index])
                            @endforeach
                        </div>
                        <div class="mt-6 flex gap-4 rounded-2xl bg-secondary p-5">
                            <x-lucide-award class="h-8 w-8 shrink-0 rounded-full bg-sage-soft p-1.5" />
                            <div><h3 class="font-semibold">Precision Refraction Guarantee</h3><p class="mt-1 text-sm text-muted-foreground">{{ $store['policy_remake'] ?? '' }}</p></div>
                        </div>
                    </section>

                        <form method="POST" action="{{ route('cart.checkout') }}" @submit="submit($event)" novalidate x-show="step > 1" x-cloak>
                            @csrf
                            {{-- Step 2: Contact & delivery --}}
                            <section x-show="step === 2" x-ref="delivery" aria-labelledby="delivery-heading">
                                <h1 id="delivery-heading" class="border-b border-border pb-4 text-2xl font-semibold">Contact & delivery</h1>
                                @if ($countries === [])
                                    <p class="mt-6 rounded-xl bg-bronze-soft p-4 text-sm">Online delivery is temporarily unavailable. Please contact our concierge at {{ $store['concierge_email'] ?? '' }} to complete your order.</p>
                                @endif

                                <div class="mt-6 rounded-2xl border border-border bg-card p-6">
                                    @auth
                                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                                            <div><p class="eyebrow text-[0.55rem] text-muted-foreground">Signed in as</p><p class="mt-1 font-medium">{{ auth()->user()->name }} · {{ auth()->user()->email }}</p></div>
                                            <span class="text-xs text-muted-foreground">Order updates go to this email</span>
                                        </div>
                                    @else
                                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                                            <h2 class="font-semibold">Contact</h2>
                                            <p class="text-xs text-muted-foreground">Have an account? <a href="{{ route('login') }}" class="font-medium text-foreground underline">Sign in</a> for faster checkout</p>
                                        </div>
                                        <div x-data="{ account: @js((bool) old('create_account')) }">
                                            <x-admin.field name="email" class="mt-4" label="Email (optional)">
                                                <input type="email" name="email" value="{{ old('email') }}" :required="account" maxlength="255" autocomplete="email" class="input">
                                            </x-admin.field>
                                            <p class="mt-1 text-[0.7rem] text-muted-foreground" x-text="account ? 'Required to create your account. We’ll also send your confirmation and tracking updates here.' : 'Add your email to receive your order confirmation and tracking updates. Without it, keep your order number to track your order.'"></p>
                                            <div class="mt-4 rounded-xl bg-secondary p-4">
                                                <label class="flex items-start gap-2 text-sm"><input type="checkbox" name="create_account" value="1" x-model="account" class="mt-0.5 accent-[var(--obsidian)]"><span><span class="font-medium">Create an account</span> <span class="text-muted-foreground">— save your details and prescription for next time and see all your orders in one place.</span></span></label>
                                                <div x-show="account" x-cloak class="mt-3">
                                                    <x-admin.field label="Choose a password" name="password"><input type="password" name="password" :required="account" :disabled="!account" minlength="8" autocomplete="new-password" class="input bg-card"></x-admin.field>
                                                    <p class="mt-1 text-[0.7rem] text-muted-foreground">At least 8 characters with letters and numbers.</p>
                                                </div>
                                            </div>
                                        </div>
                                    @endauth
                                </div>
                                <h2 class="mt-6 font-semibold">Delivery address</h2>
                                <div class="mt-6 grid gap-4 rounded-2xl border border-border bg-card p-6 sm:grid-cols-2">
                                    <x-admin.field label="Full name" name="ship_name"><input name="ship_name" value="{{ $addr('ship_name') }}" required minlength="2" maxlength="80" autocomplete="shipping name" class="input"></x-admin.field>
                                    <x-admin.field label="Phone (for the courier)" name="ship_phone"><input type="tel" name="ship_phone" value="{{ $addr('ship_phone') }}" required maxlength="20" pattern="[+\d\s\(\)\-]{7,20}" data-pattern-message="Enter a valid phone number with country code, e.g. +880 1712 345678." autocomplete="shipping tel" class="input"></x-admin.field>
                                    <x-admin.field label="Street address" name="ship_line1" class="sm:col-span-2"><input name="ship_line1" value="{{ $addr('ship_line1') }}" required maxlength="160" autocomplete="shipping address-line1" class="input"></x-admin.field>
                                    <x-admin.field label="Apartment, suite, etc. (optional)" name="ship_line2" class="sm:col-span-2"><input name="ship_line2" value="{{ $addr('ship_line2') }}" maxlength="160" autocomplete="shipping address-line2" class="input"></x-admin.field>
                                    <x-admin.field label="City" name="ship_city"><input name="ship_city" value="{{ $addr('ship_city') }}" required maxlength="80" autocomplete="shipping address-level2" class="input"></x-admin.field>
                                    <x-admin.field label="Postal code" name="ship_postal"><input name="ship_postal" x-ref="postal" x-model="postal" @input="$el.setCustomValidity('')" @change="checkEstimate()" required maxlength="12" autocomplete="shipping postal-code" class="input"></x-admin.field>
                                    <x-admin.field label="Country" name="ship_country" class="sm:col-span-2">
                                        <select name="ship_country" x-model="country" @change="checkEstimate()" required autocomplete="shipping country" class="input">
                                            <option value="">Select a country</option>
                                            @foreach ($countries as $code => $name)<option value="{{ $code }}">{{ $name }}</option>@endforeach
                                        </select>
                                    </x-admin.field>
                                    <div class="sm:col-span-2" aria-live="polite">
                                        <p x-show="estimate?.status === 'available'" x-cloak class="flex items-center gap-2 rounded-lg bg-sage-soft px-3 py-2 text-xs"><x-lucide-truck class="h-4 w-4" /><span>Estimated delivery <strong x-text="estimate?.earliest + ' – ' + estimate?.latest"></strong> via <span x-text="estimate?.courier"></span></span></p>
                                        <p x-show="estimate?.status === 'unavailable'" x-cloak class="text-xs text-destructive" x-text="estimate?.message"></p>
                                    </div>
                                    <x-admin.field label="Delivery instructions (optional)" name="customer_note" class="sm:col-span-2"><textarea name="customer_note" rows="2" maxlength="500" placeholder="Gate code, safe place, preferred delivery time…" class="input">{{ old('customer_note') }}</textarea></x-admin.field>
                                </div>
                                <p class="mt-4 flex gap-2 text-xs text-muted-foreground"><x-lucide-shield-check class="h-4 w-4 shrink-0" />A licensed optician checks every prescription before your lenses are made. If anything is missing we’ll contact you on this phone number.</p>
                            </section>

                            {{-- Step 3: Payment --}}
                            <section x-show="step === 3" x-ref="payment" aria-labelledby="payment-heading">
                                <h1 id="payment-heading" class="border-b border-border pb-4 text-2xl font-semibold">Payment</h1>
                                <div class="mt-6 flex items-start justify-between gap-4 rounded-2xl border border-border bg-card p-5 text-sm">
                                    <div>
                                        <p class="eyebrow text-[0.55rem] text-muted-foreground">Delivering to</p>
                                        <p class="mt-1" x-text="[$refs.delivery.querySelector('[name=ship_name]').value, $refs.delivery.querySelector('[name=ship_line1]').value, ($refs.delivery.querySelector('[name=ship_postal]').value + ' ' + $refs.delivery.querySelector('[name=ship_city]').value).trim()].filter(Boolean).join(', ')"></p>
                                        <p x-show="estimate?.status === 'available'" class="mt-1 text-xs text-muted-foreground">Arrives <span x-text="estimate?.earliest + ' – ' + estimate?.latest"></span></p>
                                    </div>
                                    <button type="button" @click="go(2)" class="text-xs underline">Edit</button>
                                </div>

                                <div class="mt-6 rounded-2xl border border-border bg-card p-6">
                                    <div class="flex justify-between"><h2 class="font-semibold">Payment method</h2><span class="eyebrow text-[0.55rem] text-muted-foreground"><x-lucide-lock class="mr-1 inline h-3 w-3" />Encrypted</span></div>
                                    @error('payment_method')<p class="mt-2 text-xs text-destructive">{{ $message }}</p>@enderror
                                    @error('items')<p class="mt-2 rounded-lg bg-bronze-soft p-3 text-xs">{{ $message }}</p>@enderror
                                    <div class="mt-4 space-y-3">
                                        @forelse ($paymentMethods as $method)
                                            @php($code = $method->code)
                                            <label :class="pay === @js($code) ? 'border-primary' : 'border-border bg-secondary'" class="flex cursor-pointer items-center gap-3 rounded-xl border p-4 text-sm">
                                                <input type="radio" name="payment_method" value="{{ $code }}" x-model="pay" class="accent-[var(--obsidian)]">
                                                <div class="flex-1">
                                                    <div class="font-medium">{{ $method->name }}</div>
                                                    @if ($method->description)<div class="text-xs text-muted-foreground">{{ $method->description }}</div>
                                                    @elseif ($method->type === 'installment')<div class="text-xs text-muted-foreground">${{ number_format($bill['installment'], 2) }} × 4, interest-free</div>@endif
                                                </div>
                                                @if ($method->logoUrl())
                                                    <img src="{{ $method->logoUrl() }}" alt="{{ $method->name }}" class="h-7 w-auto max-w-20 object-contain" loading="lazy">
                                                @elseif ($method->type === 'card')
                                                    <span class="flex gap-1">@foreach (['VISA', 'MC', 'AMEX'] as $b)<span class="rounded border border-border px-1.5 text-[0.6rem] font-bold">{{ $b }}</span>@endforeach</span>
                                                @endif
                                            </label>
                                            {{-- Fields of unselected methods are disabled so only the chosen method's details are submitted. --}}
                                            @if ($method->type === 'card')
                                                <fieldset x-show="pay === @js($code)" :disabled="pay !== @js($code)" class="space-y-3 rounded-xl bg-secondary p-4 text-xs"
                                                     x-data="{ num: '', fmt(v) { return v.replace(/\D/g, '').slice(0, 16).replace(/(\d{4})(?=\d)/g, '$1 '); } }">
                                                    <x-admin.field label="Cardholder name" name="card_name"><input name="card_name" value="{{ old('card_name') }}" required minlength="2" maxlength="80" autocomplete="cc-name" class="input bg-card py-2.5"></x-admin.field>
                                                    <x-admin.field label="Card number" name="card_number"><div class="relative"><input name="card_number" required pattern="[\d\s]{13,23}" data-pattern-message="Enter a valid card number (13–19 digits)." inputmode="numeric" autocomplete="cc-number" x-model="num" @input="num = fmt($event.target.value)" class="input bg-card py-2.5 font-mono"><x-lucide-credit-card class="absolute right-3 top-3 h-4 w-4 text-muted-foreground" /></div></x-admin.field>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <x-admin.field label="Expiry (MM / YY)" name="card_expiry"><input name="card_expiry" required pattern="\d{2}\s?/\s?\d{2}" data-pattern-message="Enter the expiry as MM / YY." placeholder="MM / YY" maxlength="7" inputmode="numeric" autocomplete="cc-exp" class="input bg-card py-2.5 text-center font-mono"></x-admin.field>
                                                        <x-admin.field label="CVC" name="card_cvc"><input name="card_cvc" type="password" required pattern="\d{3,4}" data-pattern-message="Enter the 3 or 4 digit code on the back of your card." maxlength="4" inputmode="numeric" autocomplete="cc-csc" class="input bg-card py-2.5 text-center font-mono"></x-admin.field>
                                                    </div>
                                                    <p class="text-[0.65rem] text-muted-foreground">{{ $method->instructions ?: 'Card details are checked in this demo checkout and never stored.' }}</p>
                                                </fieldset>
                                            @elseif ($method->type === 'installment')
                                                <div x-show="pay === @js($code)" class="space-y-2 rounded-xl bg-secondary p-3 text-[0.65rem]">
                                                    <div class="grid grid-cols-4 gap-2 text-center">
                                                        @foreach (['Today', '+2 wks', '+4 wks', '+6 wks'] as $w)<div><div class="font-mono font-semibold">${{ number_format($bill['installment'], 2) }}</div><div class="text-muted-foreground">{{ $w }}</div></div>@endforeach
                                                    </div>
                                                    @if ($method->instructions)<p class="text-muted-foreground">{{ $method->instructions }}</p>@endif
                                                </div>
                                            @elseif ($method->type === 'mobile')
                                                <fieldset x-show="pay === @js($code)" :disabled="pay !== @js($code)" class="space-y-3 rounded-xl bg-secondary p-4 text-xs">
                                                    @if ($method->instructions)<p class="whitespace-pre-line text-muted-foreground">{{ $method->instructions }}</p>@endif
                                                    @if ($method->account_number)
                                                        <div class="flex items-center justify-between gap-3 rounded-lg border border-border bg-card px-3 py-2.5">
                                                            <div><div class="text-[0.6rem] uppercase tracking-wide text-muted-foreground">Send {{ '$'.number_format($bill['total'], 2) }} to</div><div class="font-mono text-sm font-semibold">{{ $method->account_number }}</div></div>
                                                            <button type="button" x-data="{ copied: false }" @click="navigator.clipboard?.writeText(@js($method->account_number)); copied = true; setTimeout(() => copied = false, 1500)" class="text-[0.65rem] underline" x-text="copied ? 'Copied' : 'Copy'"></button>
                                                        </div>
                                                    @endif
                                                    <div class="grid gap-3 sm:grid-cols-2">
                                                        <x-admin.field :label="'Your '.$method->name.' number'" name="payer_account"><input name="payer_account" value="{{ old('payer_account') }}" required pattern="(\+?88)?01[3-9]\d{8}" data-pattern-message="Enter an 11-digit mobile number, e.g. 01XXXXXXXXX." placeholder="01XXXXXXXXX" maxlength="14" inputmode="tel" autocomplete="tel" class="input bg-card py-2.5 font-mono"></x-admin.field>
                                                        <x-admin.field label="Transaction ID" name="payer_reference"><input name="payer_reference" value="{{ old('payer_reference') }}" required pattern="[A-Za-z0-9]{6,30}" data-pattern-message="Enter the transaction ID from your payment confirmation." placeholder="e.g. 9BG7XK2LQ1" maxlength="30" autocomplete="off" class="input bg-card py-2.5 font-mono uppercase"></x-admin.field>
                                                    </div>
                                                    <p class="text-[0.65rem] text-muted-foreground">Your order is confirmed once we verify the payment.</p>
                                                </fieldset>
                                            @elseif ($method->instructions || $method->account_number)
                                                <div x-show="pay === @js($code)" class="space-y-1 rounded-xl bg-secondary p-4 text-[0.65rem] text-muted-foreground">
                                                    @if ($method->instructions)<p class="whitespace-pre-line">{{ $method->instructions }}</p>@endif
                                                    @if ($method->account_number)<p>Account: <span class="font-mono font-semibold text-foreground">{{ $method->account_number }}</span></p>@endif
                                                </div>
                                            @endif
                                        @empty
                                            <p class="rounded-xl bg-secondary p-4 text-xs text-muted-foreground">Online checkout is temporarily unavailable. Please contact us to place your order.</p>
                                        @endforelse
                                    </div>
                                    <button :disabled="busy" class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 text-sm font-medium text-primary-foreground disabled:opacity-70">
                                        <span x-text="busy ? 'Placing order…' : @js('Place order · $'.number_format($bill['total'], 2).' '.($store['currency'] ?? 'USD'))"></span> <x-lucide-arrow-right class="h-4 w-4" />
                                    </button>
                                    <p class="mt-3 text-center text-[0.65rem] text-muted-foreground">By placing your order you agree to our Terms of Service and {{ $store['warranty_days'] ?? 365 }}-day warranty. You can cancel free of charge until an optician starts work on it.</p>
                                </div>
                            </section>
                        </form>
                </div>

                {{-- Order summary (all steps) --}}
                <aside class="space-y-5 lg:sticky lg:top-20 lg:self-start">
                    @if ($bill['member_discount'] > 0)
                        <div class="flex gap-3 rounded-2xl border border-bronze bg-bronze-soft p-5 text-sm"><x-lucide-gem class="h-5 w-5 shrink-0" /><div><div class="font-semibold">Member privilege active</div>Buy 1 Get 1 applied — your lowest-priced pair is complimentary.</div></div>
                    @endif
                    <div class="rounded-2xl border border-border bg-card p-6">
                        <div class="flex justify-between"><h2 class="text-xl font-semibold">Order summary</h2><span class="eyebrow text-[0.55rem] text-muted-foreground">{{ $store['currency'] ?? 'USD' }}</span></div>
                        <dl class="mt-5 space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-muted-foreground">Items ({{ $items->sum('qty') }})</dt><dd class="font-medium">${{ number_format($bill['subtotal'], 2) }}</dd></div>
                            @if ($bill['member_discount'] > 0)<div class="flex justify-between"><dt class="text-muted-foreground">Member discount</dt><dd class="font-medium">-${{ number_format($bill['member_discount'], 2) }}</dd></div>@endif
                            @if ($bill['gift_discount'] > 0)<div class="flex justify-between"><dt class="text-muted-foreground">Code ({{ $bill['coupon'] }})</dt><dd class="font-medium">-${{ number_format($bill['gift_discount'], 2) }}</dd></div>@endif
                            <div class="flex justify-between"><dt class="text-muted-foreground">Lens surfacing & fitting</dt><dd class="font-medium">Free</dd></div>
                            <div class="flex justify-between"><dt class="text-muted-foreground">Insured express delivery</dt><dd class="font-medium">Free</dd></div>
                            <div class="flex justify-between"><dt class="text-muted-foreground">Duties & VAT</dt><dd class="font-medium">{{ ($store['prices_tax_inclusive'] ?? true) ? 'Included' : 'Calculated at dispatch' }}</dd></div>
                        </dl>
                        <div class="mt-5 flex items-end justify-between border-t border-border pt-5"><div class="font-semibold">Total</div><div class="text-right font-display text-3xl font-semibold">${{ number_format($bill['total'], 2) }}</div></div>

                        <div class="mt-5 divide-y divide-border border-t border-border text-sm" x-data="{ openCode: {{ $errors->has('code') ? 'true' : 'false' }}, openIns: false }">
                            <button type="button" @click="openCode = !openCode" :aria-expanded="openCode" class="flex w-full items-center gap-2 py-3"><x-lucide-tag class="h-4 w-4" />Promo code or gift card<x-lucide-chevron-down class="ml-auto h-4 w-4" /></button>
                            <form x-show="openCode" x-cloak method="POST" action="{{ route('cart.coupon') }}" class="pb-3">
                                @csrf
                                <div class="flex gap-2">
                                    <label for="coupon-code" class="sr-only">Code</label>
                                    <input id="coupon-code" name="code" value="{{ old('code', $coupon?->code) }}" placeholder="Enter code" class="input flex-1">
                                    <button class="btn btn-sm">Apply</button>
                                </div>
                                @error('code')<p class="mt-1 text-xs text-destructive">{{ $message }}</p>@enderror
                            </form>
                            <button type="button" @click="openIns = !openIns" :aria-expanded="openIns" class="flex w-full items-center gap-2 py-3"><x-lucide-heart-pulse class="h-4 w-4" />Insurance / HSA / FSA<x-lucide-chevron-down class="ml-auto h-4 w-4" /></button>
                            <p x-show="openIns" x-cloak class="pb-3 text-xs text-muted-foreground">Prescription eyewear is HSA/FSA eligible. Pay with your HSA/FSA card, or use the itemised receipt in your confirmation email for out-of-network reimbursement.</p>
                        </div>
                    </div>

                    <div x-show="step < 3">
                        <button type="button" @click="go(step + 1)" class="flex w-full items-center justify-center gap-2 rounded-xl bg-primary py-4 text-sm font-medium text-primary-foreground">
                            <span x-text="step === 1 ? 'Checkout' : 'Continue to payment'"></span> <x-lucide-arrow-right class="h-4 w-4" />
                        </button>
                        @guest
                            <p x-show="step === 1" class="mt-3 text-center text-xs text-muted-foreground">No account needed — check out as a guest, or <a href="{{ route('login') }}" class="font-medium text-foreground underline">sign in</a> to use your saved details.</p>
                        @endguest
                    </div>

                    <div class="grid grid-cols-3 gap-2 text-center text-[0.65rem] text-muted-foreground">
                        @foreach ([['lock', 'Secure checkout'], ['target', 'Optician-checked Rx'], ['refresh-cw', '30-day free remake']] as [$icon, $label])
                            <div>@svg('lucide-'.$icon, 'mx-auto mb-1 h-4 w-4'){{ $label }}</div>
                        @endforeach
                    </div>
                </aside>
            </div>
        </div>
    @endif
</x-layouts.app>
