@php
    $editing = $template->exists;
    $samples = \App\Models\EmailTemplate::SAMPLE_TOKENS;
@endphp
<x-layouts.admin :title="$editing ? $template->name : 'New template'">
    <form method="POST" action="{{ $editing ? route('admin.email.update', $template) : route('admin.email.store') }}" class="space-y-6"
          x-data="{ meta: @js(['name' => old('name', $template->name), 'hook' => old('hook', $template->hook)]),
                    subject: @js(old('subject', $template->subject)), preheader: @js(old('preheader', $template->preheader)),
                    blocks: @js(old('blocks') ? json_decode(old('blocks'), true) : ($template->blocks ?? [])), sel: 0, samples: @js($samples), status: @js(old('status', $template->status)),
                    fill(t) { return Object.entries(this.samples).reduce((s, [k, v]) => s.split(k).join(v), t || ''); },
                    add(type) { const defaults = { 'CTA Button': 'Learn more', Heading: 'New heading', Paragraph: 'Write something…', Footer: 'REFRA Atelier Optics' };
                                this.blocks.push({ id: String(Date.now()), type, text: defaults[type] ?? '' }); this.sel = this.blocks.length - 1; },
                    move(i, d) { const j = i + d; if (j < 0 || j >= this.blocks.length) return; [this.blocks[i], this.blocks[j]] = [this.blocks[j], this.blocks[i]]; this.sel = j; },
                    get editable() { return ['Heading', 'Paragraph', 'CTA Button', 'Footer'].includes(this.blocks[this.sel]?.type); } }">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="blocks" :value="JSON.stringify(blocks)">
        <input type="hidden" name="status" :value="status">
        <x-admin.page-head :eyebrow="($editing ? $template->status : 'Draft').' · '.$template->hook" :title="$editing ? $template->name : 'New template'">
            <a href="{{ route('admin.email.index') }}" class="btn-ghost btn-sm">Back</a>
            <button type="submit" @click="status = 'Draft'" class="btn-ghost btn-sm">Save draft</button>
            <button type="submit" @click="status = 'Live'" class="btn btn-sm">Publish</button>
        </x-admin.page-head>

        <x-admin.panel title="Template specification & routing">
            <div class="grid gap-3 md:grid-cols-5">
                <x-admin.field label="Name" name="name"><input name="name" x-model="meta.name" required class="input"></x-admin.field>
                <x-admin.field label="Internal description" name="description"><input name="description" value="{{ old('description', $template->description) }}" class="input"></x-admin.field>
                <x-admin.field label="Category" name="category"><select name="category" class="input">@foreach (config('store.email_categories') as $c)<option @selected(old('category', $template->category) === $c)>{{ $c }}</option>@endforeach</select></x-admin.field>
                <x-admin.field label="Locale" name="locale"><select name="locale" class="input">@foreach (['en-GB', 'en-US', 'fr-CH', 'de-CH', 'it-IT'] as $l)<option @selected(old('locale', $template->locale) === $l)>{{ $l }}</option>@endforeach</select></x-admin.field>
                <x-admin.field label="Trigger hook" name="hook"><input name="hook" x-model="meta.hook" required class="input font-mono"></x-admin.field>
            </div>
        </x-admin.panel>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-6">
                <x-admin.panel title="Subject & envelope">
                    <x-admin.field label="Subject line" name="subject"><input name="subject" x-model="subject" required class="input"></x-admin.field>
                    <div class="mt-2 flex flex-wrap gap-1">@foreach (config('store.email_tokens') as $token)<button type="button" @click="subject += ' {{ $token }}'" class="rounded bg-secondary px-1.5 py-0.5 font-mono text-[0.6rem]">+ {{ $token }}</button>@endforeach</div>
                    <div class="mt-3"><x-admin.field label="Preheader" name="preheader"><input name="preheader" x-model="preheader" class="input"></x-admin.field></div>
                </x-admin.panel>
                <x-admin.panel title="Modular layout engine">
                    <div class="mb-3 flex flex-wrap gap-1">@foreach (config('store.email_block_types') as $type)<button type="button" @click="add('{{ $type }}')" class="rounded-md border border-border px-2 py-1 text-xs">+ {{ $type }}</button>@endforeach</div>
                    <div class="space-y-1.5">
                        <template x-for="(b, i) in blocks" :key="b.id">
                            <div @click="sel = i" :class="sel === i ? 'border-primary bg-secondary' : 'border-border'" class="flex cursor-pointer items-center gap-2 rounded-md border px-3 py-2 text-sm">
                                <span class="flex-1" x-text="b.type"></span>
                                <button type="button" @click.stop="move(i, -1)" aria-label="Move up"><x-lucide-arrow-up class="h-3.5 w-3.5" /></button>
                                <button type="button" @click.stop="move(i, 1)" aria-label="Move down"><x-lucide-arrow-down class="h-3.5 w-3.5" /></button>
                                <button type="button" @click.stop="blocks.splice(i, 1); sel = 0" aria-label="Delete"><x-lucide-trash-2 class="h-3.5 w-3.5" /></button>
                            </div>
                        </template>
                    </div>
                    <template x-if="editable">
                        <div class="mt-4">
                            <label class="block text-xs"><span class="field-label" x-text="'Edit ' + blocks[sel].type"></span><textarea rows="4" class="input" x-model="blocks[sel].text"></textarea></label>
                            <div class="mt-2 flex flex-wrap gap-1">@foreach (config('store.email_tokens') as $token)<button type="button" @click="blocks[sel].text += ' {{ $token }}'" class="rounded bg-secondary px-1.5 py-0.5 font-mono text-[0.6rem]">+ {{ $token }}</button>@endforeach</div>
                        </div>
                    </template>
                </x-admin.panel>
            </div>
            <x-admin.panel title="Live inbox preview" class="h-fit xl:sticky xl:top-20">
                <div class="mb-3 rounded-lg border border-border p-3 text-xs">
                    <div class="flex justify-between"><span class="font-semibold">{{ $store['store_name'] ?? 'REFRA' }}</span><span class="text-muted-foreground">{{ now()->format('H:i') }}</span></div>
                    <div class="font-medium" x-text="fill(subject)"></div><div class="truncate text-muted-foreground" x-text="preheader"></div>
                </div>
                <div class="overflow-hidden rounded-xl border border-border bg-card">
                    <div class="space-y-3 p-5">
                        <template x-for="b in blocks" :key="b.id">
                            <div>
                                <div x-show="b.type === 'Logo'" class="flex items-center gap-2"><x-site.logo-mark class="h-6 w-6" /><span class="font-display text-sm font-semibold tracking-[0.3em]">REFRA</span></div>
                                <h3 x-show="b.type === 'Heading'" class="text-lg font-semibold" x-text="fill(b.text)"></h3>
                                <p x-show="b.type === 'Paragraph'" class="text-sm text-muted-foreground" x-text="fill(b.text)"></p>
                                <span x-show="b.type === 'CTA Button'" class="inline-block rounded-md bg-primary px-4 py-2 text-xs text-primary-foreground" x-text="fill(b.text)"></span>
                                <div x-show="b.type === 'Order Summary'" class="rounded-lg bg-secondary p-3 text-xs"><div class="flex justify-between"><span>Apex 01 Titanium</span><span>$540</span></div><div class="mt-1 flex justify-between font-semibold"><span>Total</span><span>$620</span></div></div>
                                <div x-show="b.type === 'Lens Specs'" class="grid grid-cols-3 gap-2 font-mono text-[0.65rem]"><div class="rounded bg-secondary p-2">OD -2.75</div><div class="rounded bg-secondary p-2">OS -3.00</div><div class="rounded bg-secondary p-2">Index 1.67</div></div>
                                <hr x-show="b.type === 'Divider'" class="border-border">
                                <div x-show="b.type === 'Image'" class="h-24 rounded-lg bg-obsidian"></div>
                                <p x-show="b.type === 'Footer'" class="border-t border-border pt-3 text-[0.65rem] text-muted-foreground" x-text="fill(b.text)"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </x-admin.panel>
        </div>
    </form>
    @if ($editing)
        <form method="POST" action="{{ route('admin.email.destroy', $template) }}" onsubmit="return confirm('Delete this template?')" class="flex justify-end">@csrf @method('DELETE')<button class="text-xs text-destructive underline">Delete template</button></form>
    @endif
</x-layouts.admin>
