{{-- One configured frame in the shopping bag. Expects $item, $index, $free. --}}
<div class="rounded-2xl border border-border bg-card p-5">
    @if ($free)<span class="eyebrow mb-3 inline-block rounded-full bg-bronze-soft px-3 py-1 text-[0.55rem]">★ Member privilege: 1+1 complimentary pair</span>@endif
    <div class="flex flex-col gap-5 sm:flex-row">
        <img src="{{ $item['product']->imageUrl() }}" alt="{{ $item['product']->name }}" class="h-28 w-full shrink-0 rounded-lg object-cover sm:w-36">
        <div class="min-w-0 flex-1">
            <div class="flex justify-between gap-3">
                <div>
                    <p class="eyebrow text-[0.55rem] text-muted-foreground">SKU: {{ $item['product']->sku }}</p>
                    <h3 class="mt-1 font-semibold"><a href="{{ route('products.show', $item['product']) }}" class="hover:underline">{{ $item['product']->name }}</a></h3>
                    <p class="text-xs text-muted-foreground">{{ $item['finish'] }}{{ $item['size'] ? ' · Size '.$item['size'] : '' }}</p>
                </div>
                <div class="text-right font-display font-semibold">
                    @if ($free && $item['qty'] === 1)Free<div class="text-xs font-normal text-muted-foreground line-through">${{ number_format($item['unit_price'], 2) }}</div>
                    @else${{ number_format($item['line_total'], 2) }}@endif
                </div>
            </div>
            @if ($item['lens'])
                @php($rx = $item['lens']['rx'] ?? [])
                <div class="mt-3 rounded-lg border border-border bg-secondary p-3 text-xs">
                    <span class="eyebrow text-[0.55rem]">Lens configuration</span>
                    <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ([
                            'Power' => $item['power_label'] ?? '—',
                            'Lens' => $item['lens_label'] ?? '—',
                            'OD / OS SPH' => ! empty($rx['r_sph']) ? $rx['r_sph'].' / '.($rx['l_sph'] ?? '—') : match ($item['lens']['rx_mode'] ?? null) { 'upload' => 'From upload', 'later' => 'Send within '.($store['rx_submission_days'] ?? 15).' days', default => '—' },
                            'CYL / Axis' => ! empty($rx['r_axis']) ? ($rx['r_cyl'] ?? '0.00').' × '.$rx['r_axis'] : '—',
                        ] as $k => $v)
                            <div class="rounded bg-card p-2"><div class="text-muted-foreground">{{ $k }}</div><div class="font-mono font-semibold">{{ $v }}</div></div>
                        @endforeach
                    </div>
                    @if (($item['lens']['rx_mode'] ?? null) === 'upload')<p class="mt-2 flex items-center gap-1"><x-lucide-file-text class="h-3 w-3" />Prescription attached: {{ $item['lens']['file_name'] ?? '' }}</p>@endif
                </div>
            @else
                <p class="mt-3 text-xs text-muted-foreground">Frame only · <a href="{{ route('products.show', $item['product']) }}" class="underline">add lenses</a></p>
            @endif
            <div class="mt-4 flex flex-wrap items-center gap-4 text-xs">
                <a href="{{ route('products.show', $item['product']) }}" class="flex items-center gap-1 underline"><x-lucide-pencil class="h-3 w-3" />Change lenses</a>
                <form method="POST" action="{{ route('cart.destroy', $item['key']) }}">@csrf @method('DELETE')<button class="flex items-center gap-1 underline"><x-lucide-trash-2 class="h-3 w-3" />Remove</button></form>
                <form method="POST" action="{{ route('cart.update', $item['key']) }}" class="ml-auto flex items-center gap-2">
                    @csrf @method('PATCH')
                    <label for="qty-{{ $index }}">Qty:</label>
                    <input id="qty-{{ $index }}" type="number" name="qty" min="1" max="10" value="{{ $item['qty'] }}" onchange="this.form.submit()" class="w-14 rounded border border-input bg-card px-2 py-1 text-center">
                </form>
            </div>
        </div>
    </div>
</div>
