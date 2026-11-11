@php($sectionData = $sections->map(fn ($s) => ['id' => $s->id, 'label' => $s->label, 'key' => $s->key, 'is_enabled' => $s->is_enabled, 'edit' => route('admin.content.edit', $s), 'headline' => $s->get('headline')])->values())
<x-layouts.admin title="Website Content">
    <form method="POST" action="{{ route('admin.content.reorder') }}" class="space-y-6"
          x-data="{ secs: @js($sectionData), move(i, d) { const j = i + d; if (j < 0 || j >= this.secs.length) return; [this.secs[i], this.secs[j]] = [this.secs[j], this.secs[i]]; } }">
        @csrf @method('PUT')
        <x-admin.page-head eyebrow="CMS" title="Website Content">
            <a href="{{ route('home') }}" target="_blank" class="btn-ghost btn-sm">Preview homepage ↗</a>
            <button class="btn btn-sm">Publish order & visibility</button>
        </x-admin.page-head>
        <div class="grid gap-6 xl:grid-cols-[1fr_1.2fr]">
            <x-admin.panel title="Homepage architecture">
                <div class="space-y-2">
                    <template x-for="(s, i) in secs" :key="s.id">
                        <div class="flex items-center gap-2 rounded-lg border border-border p-2.5 text-sm">
                            <input type="hidden" :name="`sections[${i}][id]`" :value="s.id">
                            <input type="hidden" :name="`sections[${i}][is_enabled]`" :value="s.is_enabled ? 1 : 0">
                            <span class="font-mono text-xs text-muted-foreground" x-text="String(i + 1).padStart(2, '0')"></span>
                            <span class="flex-1" x-text="s.label"></span>
                            <a :href="s.edit" class="text-xs underline">Edit</a>
                            <button type="button" @click="move(i, -1)" aria-label="Move up"><x-lucide-arrow-up class="h-3.5 w-3.5" /></button>
                            <button type="button" @click="move(i, 1)" aria-label="Move down"><x-lucide-arrow-down class="h-3.5 w-3.5" /></button>
                            <span class="switch"><input type="checkbox" x-model="s.is_enabled" :aria-label="`Show ${s.label}`"></span>
                        </div>
                    </template>
                </div>
            </x-admin.panel>
            <x-admin.panel title="Live canvas staging preview">
                <div class="space-y-1.5 rounded-xl border border-border p-3">
                    <template x-for="(s, i) in secs.filter(x => x.is_enabled)" :key="s.id">
                        <div :class="s.key === 'hero' ? 'rounded-lg bg-obsidian p-6 text-on-dark' : 'rounded-lg bg-secondary p-3 text-xs'">
                            <template x-if="s.key === 'hero'"><div><p class="eyebrow text-[0.5rem] text-bronze">Hero</p><p class="mt-1 font-display text-xl font-semibold uppercase" x-text="s.headline"></p></div></template>
                            <span x-show="s.key !== 'hero'" x-text="s.label"></span>
                        </div>
                    </template>
                </div>
            </x-admin.panel>
        </div>
    </form>
    <x-admin.panel title="Publication audit trail">
        <ul class="space-y-2 text-sm">
            @forelse ($activity as $log)
                <li class="flex justify-between border-b border-border pb-2"><span>{{ $log->action }}<span class="block text-xs text-muted-foreground">{{ $log->user?->email ?? 'system' }}</span></span><span class="text-xs text-muted-foreground">{{ $log->created_at->format('d M, H:i') }}</span></li>
            @empty
                <li class="text-muted-foreground">No content changes yet.</li>
            @endforelse
        </ul>
    </x-admin.panel>
</x-layouts.admin>
