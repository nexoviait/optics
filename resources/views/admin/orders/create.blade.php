@php
    $productData = $products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'price' => (float) $p->price, 'stock' => $p->stock])->values();
    $lensData = $lensOptions->map(fn ($l) => ['code' => $l->code, 'label' => $l->label, 'price' => (float) $l->price])->values();
    $customerData = $customers->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'email' => $c->email])->values();
@endphp
<x-layouts.admin title="Create Manual Order">
    <form method="POST" action="{{ route('admin.orders.store') }}" class="space-y-6"
          x-data="{ q: '', customer: {{ (int) old('customer_id', 0) ?: 'null' }}, customers: @js($customerData), products: @js($productData), lenses: @js($lensData), bogo: @js($memberBogo),
                    lines: [{ product_id: {{ $products->first()?->id ?? 'null' }}, lens: '{{ $lensOptions->first()?->code }}', qty: 1 }],
                    get filtered() { const t = this.q.toLowerCase(); return this.customers.filter(c => (c.name + c.email).toLowerCase().includes(t)); },
                    unit(l) { return (this.products.find(p => p.id == l.product_id)?.price ?? 0) + (this.lenses.find(x => x.code === l.lens)?.price ?? 0); },
                    get units() { return this.lines.flatMap(l => Array(Number(l.qty) || 0).fill(this.unit(l))); },
                    get subtotal() { return this.units.reduce((a, b) => a + b, 0); },
                    get member() { return this.bogo && this.units.length >= 2 ? Math.min(...this.units) : 0; } }">
        @csrf
        <x-admin.page-head eyebrow="Concierge / phone" title="Create Manual Order"><a href="{{ route('admin.orders.index') }}" class="btn-ghost btn-sm">Back</a></x-admin.page-head>
        <div class="grid gap-6 xl:grid-cols-[1.5fr_1fr]">
            <div class="space-y-6">
                <x-admin.panel title="Customer directory search">
                    <input class="input" placeholder="Search by name or email" x-model="q" aria-label="Search customers">
                    <input type="hidden" name="customer_id" :value="customer">
                    <div class="mt-3 max-h-64 space-y-1 overflow-y-auto">
                        @if ($customers->isEmpty())<p class="text-sm text-muted-foreground">No client accounts yet. <a href="{{ route('admin.customers.index') }}" class="underline">Add a client</a> first.</p>@endif
                        <template x-for="c in filtered" :key="c.id">
                            <button type="button" @click="customer = c.id" :class="customer === c.id ? 'border-primary bg-secondary' : 'border-border'" class="flex w-full justify-between rounded-md border px-3 py-2 text-left text-sm"><span x-text="c.name"></span><span class="text-xs text-muted-foreground" x-text="c.email"></span></button>
                        </template>
                    </div>
                    @error('customer_id')<p class="mt-2 text-xs text-destructive">{{ $message }}</p>@enderror
                </x-admin.panel>
                <x-admin.panel title="Line items">
                    <x-slot:action><button type="button" @click="lines.push({ product_id: products[0]?.id, lens: lenses[0]?.code, qty: 1 })" class="btn-ghost btn-sm">+ Add item</button></x-slot:action>
                    <div class="space-y-3">
                        <template x-for="(l, i) in lines" :key="i">
                            <div class="grid grid-cols-[1fr_1fr_70px_auto] gap-2">
                                <select :name="`items[${i}][product_id]`" x-model.number="l.product_id" class="input" aria-label="Frame"><template x-for="p in products" :key="p.id"><option :value="p.id" x-text="`${p.name} — $${p.price} (${p.stock} in stock)`" :selected="p.id == l.product_id"></option></template></select>
                                <select :name="`items[${i}][lens]`" x-model="l.lens" class="input" aria-label="Lens"><template x-for="x in lenses" :key="x.code"><option :value="x.code" x-text="x.label + (x.price ? ` (+$${x.price})` : '')" :selected="x.code === l.lens"></option></template></select>
                                <input type="number" min="1" max="10" :name="`items[${i}][qty]`" x-model.number="l.qty" class="input" aria-label="Quantity">
                                <button type="button" @click="lines.splice(i, 1)" aria-label="Remove"><x-lucide-trash-2 class="h-4 w-4" /></button>
                            </div>
                        </template>
                    </div>
                    <p class="mt-3 text-xs text-muted-foreground">The client submits their prescription within {{ $store['rx_submission_days'] ?? 15 }} days; it attaches to their account.</p>
                </x-admin.panel>
            </div>
            <x-admin.panel title="Payment settlement" class="h-fit">
                <x-admin.field label="Method" name="payment_method">
                    <select name="payment_method" class="input">@foreach (['phone', 'link', 'cod', 'boutique'] as $m)<option value="{{ $m }}" @selected(old('payment_method') === $m)>{{ config("store.payment_methods.$m.label") }}</option>@endforeach</select>
                </x-admin.field>
                <dl class="mt-5 space-y-1 border-t border-border pt-4 text-sm">
                    <div class="flex justify-between"><dt class="text-muted-foreground">Subtotal</dt><dd x-text="'$' + subtotal.toFixed(2)"></dd></div>
                    <div x-show="member > 0" class="flex justify-between"><dt class="text-muted-foreground">1+1 privilege</dt><dd x-text="'-$' + member.toFixed(2)"></dd></div>
                </dl>
                <div class="mt-3 flex justify-between"><span class="text-sm">Total</span><span class="font-display text-2xl font-semibold" x-text="'$' + (subtotal - member).toFixed(2)"></span></div>
                <p class="mt-1 text-[0.65rem] text-muted-foreground">Final totals are re-priced on the server from the catalog.</p>
                <button class="btn mt-5 w-full py-3" :disabled="!customer || !lines.length">Create order</button>
            </x-admin.panel>
        </div>
    </form>
</x-layouts.admin>
