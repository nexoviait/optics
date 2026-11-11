@php
    $blank = ['id' => null, 'sku' => '', 'name' => '', 'substrate' => 'MR-8', 'refractive_index' => '1.60', 'abbe' => 41, 'geometry' => 'Aspheric', 'coatings' => ['Hard coat', 'Anti-reflective'], 'labs' => ['Zürich'], 'stock' => 0, 'low_stock_threshold' => 50];
@endphp
<x-layouts.admin title="Optical Lenses">
    <div x-data="{ open: {{ $errors->any() && old('_form') === 'sku' ? 'true' : 'false' }}, form: @js(old('_form') === 'sku' ? array_merge($blank, old()) : $blank),
                   edit(sku) { this.form = JSON.parse(JSON.stringify(sku)); this.open = true; }, create() { this.form = JSON.parse(JSON.stringify(@js($blank))); this.open = true; },
                   toggle(list, v) { this.form[list].includes(v) ? this.form[list] = this.form[list].filter(x => x !== v) : this.form[list].push(v); } }" class="space-y-6">
        <x-admin.page-head eyebrow="Catalog" title="Optical Lenses">
            <button type="button" @click="create()" class="btn btn-sm"><x-lucide-plus class="h-3.5 w-3.5" />Add Lens SKU</button>
        </x-admin.page-head>

        <x-admin.panel title="Lens SKU directory (lab inventory)">
            <div class="table-scroll">
                <table class="data-table">
                    <thead><tr><th>SKU</th><th>Lens</th><th>Index</th><th>Abbe</th><th>Surface geometry</th><th>Coatings</th><th>Lab stock</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($skus as $sku)
                            <tr>
                                <td class="font-mono text-xs">{{ $sku->sku }}</td>
                                <td class="font-medium">{{ $sku->name }}</td>
                                <td class="font-mono">{{ $sku->refractive_index }}</td>
                                <td class="font-mono">{{ $sku->abbe }}</td>
                                <td>{{ $sku->geometry }}</td>
                                <td class="text-xs">{{ implode(' + ', $sku->coatings ?? []) }}</td>
                                <td><x-admin.status :s="$sku->isLowStock() ? 'Low' : 'OK'" /> <span class="ml-1 font-mono text-xs">{{ $sku->stock }}</span></td>
                                <td class="whitespace-nowrap text-right">
                                    <button type="button" @click="edit(@js($sku->only(['id', 'sku', 'name', 'substrate', 'refractive_index', 'abbe', 'geometry', 'stock', 'low_stock_threshold']) + ['coatings' => $sku->coatings ?? [], 'labs' => $sku->labs ?? []]))" class="text-xs underline">Edit</button>
                                    <form method="POST" action="{{ route('admin.lenses.skus.destroy', $sku) }}" class="inline" onsubmit="return confirm('Delete this lens SKU?')">@csrf @method('DELETE')<button class="ml-2 text-xs text-destructive underline">Delete</button></form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-muted-foreground">No lens SKUs yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-admin.panel>

        <form method="POST" action="{{ route('admin.lenses.pricing.update') }}">
            @csrf @method('PUT')
            <x-admin.panel title="Storefront lens pricing (used by the lens drawer & checkout)">
                <x-slot:action><button class="btn btn-sm">Save pricing</button></x-slot:action>
                <div class="grid gap-6 xl:grid-cols-2">
                    @foreach (['power_types' => ['Power types', $powerTypes], 'lens_options' => ['Lens options', $lensOptions]] as $group => [$heading, $rows])
                        <div>
                            <h3 class="eyebrow mb-2 text-muted-foreground">{{ $heading }}</h3>
                            <div class="space-y-2">
                                @foreach ($rows as $row)
                                    <div class="grid grid-cols-[1fr_1.4fr_90px_auto] items-center gap-2 rounded-lg border border-border p-2">
                                        <input name="{{ $group }}[{{ $row->id }}][label]" value="{{ $row->label }}" aria-label="Label" class="input py-1.5">
                                        <input name="{{ $group }}[{{ $row->id }}][description]" value="{{ $row->description }}" aria-label="Description" class="input py-1.5 text-xs">
                                        <input type="number" step="0.01" min="0" name="{{ $group }}[{{ $row->id }}][price]" value="{{ (float) $row->price }}" aria-label="Price" class="input py-1.5 font-mono">
                                        <label class="switch" title="Active"><input type="hidden" name="{{ $group }}[{{ $row->id }}][is_active]" value="0"><input type="checkbox" name="{{ $group }}[{{ $row->id }}][is_active]" value="1" @checked($row->is_active)></label>
                                        @if ($group === 'lens_options')
                                            <input name="lens_options[{{ $row->id }}][badge]" value="{{ $row->badge }}" placeholder="Badge (e.g. Most popular)" aria-label="Badge" class="input py-1.5 text-xs">
                                            <textarea name="lens_options[{{ $row->id }}][features]" rows="3" placeholder="One feature per line" aria-label="Features" class="input col-span-3 py-1.5 text-xs">{{ implode("\n", $row->features ?? []) }}</textarea>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="mt-3 text-xs text-muted-foreground">Prices are surcharges on top of the frame price. Inactive options disappear from the storefront immediately.</p>
            </x-admin.panel>
        </form>

        {{-- Add / edit drawer --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50" @keydown.escape.window="open = false">
            <div class="absolute inset-0 bg-obsidian/40" @click="open = false"></div>
            <aside class="absolute inset-y-0 right-0 w-full overflow-y-auto bg-background p-5 sm:max-w-md">
                <div class="flex items-center justify-between"><h2 class="font-display text-lg font-semibold" x-text="form.id ? 'Edit Lens SKU' : 'Add Lens SKU'"></h2><button type="button" @click="open = false" aria-label="Close"><x-lucide-x class="h-4 w-4" /></button></div>
                <form method="POST" :action="form.id ? '{{ url('admin/lenses/skus') }}/' + form.id : '{{ route('admin.lenses.skus.store') }}'" class="mt-5 space-y-5">
                    @csrf
                    <input type="hidden" name="_method" :value="form.id ? 'PUT' : 'POST'">
                    <input type="hidden" name="_form" value="sku">
                    <x-admin.field label="Lens name"><input name="name" x-model="form.name" required class="input"></x-admin.field>
                    <x-admin.field label="SKU (auto-generated if empty)"><input name="sku" x-model="form.sku" class="input font-mono"></x-admin.field>
                    <div class="grid grid-cols-2 gap-3">
                        <x-admin.field label="Substrate"><select name="substrate" x-model="form.substrate" class="input">@foreach (config('store.lens_substrates') as $s)<option>{{ $s }}</option>@endforeach</select></x-admin.field>
                        <x-admin.field label="Refractive index"><select name="refractive_index" x-model="form.refractive_index" class="input">@foreach (['1.50', '1.52', '1.59', '1.60', '1.67', '1.74'] as $i)<option>{{ $i }}</option>@endforeach</select></x-admin.field>
                    </div>
                    <x-admin.field label="Surface geometry"><input name="geometry" x-model="form.geometry" required class="input"></x-admin.field>
                    <label class="block text-xs"><span class="field-label">Abbe dispersion calibration · <span x-text="form.abbe"></span></span><input type="range" name="abbe" min="20" max="65" x-model.number="form.abbe" class="w-full accent-[var(--obsidian)]"></label>
                    <div>
                        <p class="field-label">Optical coating stack</p>
                        <div class="space-y-1.5">
                            @foreach (config('store.lens_coatings') as $i => $coating)
                                <button type="button" @click="toggle('coatings', '{{ $coating }}')" :class="form.coatings.includes('{{ $coating }}') ? 'border-primary bg-secondary' : 'border-border'" class="flex w-full items-center justify-between rounded-md border px-3 py-2 text-sm"><span>L{{ $i + 1 }} · {{ $coating }}</span><span x-show="form.coatings.includes('{{ $coating }}')">✓</span></button>
                            @endforeach
                        </div>
                        <template x-for="c in form.coatings"><input type="hidden" name="coatings[]" :value="c"></template>
                    </div>
                    <div>
                        <p class="field-label">Lab partner dispatch nodes</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (config('store.lens_labs') as $lab)
                                <button type="button" @click="toggle('labs', '{{ $lab }}')" :class="form.labs.includes('{{ $lab }}') ? 'border-primary bg-primary text-primary-foreground' : 'border-border'" class="rounded-full border px-3 py-1 text-xs">{{ $lab }}</button>
                            @endforeach
                        </div>
                        <template x-for="l in form.labs"><input type="hidden" name="labs[]" :value="l"></template>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <x-admin.field label="Lab stock"><input type="number" min="0" name="stock" x-model.number="form.stock" class="input"></x-admin.field>
                        <x-admin.field label="Low-stock threshold"><input type="number" min="0" name="low_stock_threshold" x-model.number="form.low_stock_threshold" class="input"></x-admin.field>
                    </div>
                    <button class="btn w-full py-3" x-text="form.id ? 'Save Lens SKU' : 'Create Lens SKU'"></button>
                </form>
            </aside>
        </div>
    </div>
</x-layouts.admin>
