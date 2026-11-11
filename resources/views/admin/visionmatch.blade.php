@php($stepData = $steps->map(fn ($s) => ['id' => $s->id, 'key' => $s->key, 'title' => $s->title, 'options' => $s->options, 'is_required' => $s->is_required])->values())
<x-layouts.admin title="REFRA VisionMatch">
    <form method="POST" action="{{ route('admin.visionmatch.update') }}" class="space-y-6"
          x-data="{ steps: @js($stepData), active: 0,
                    move(i, d) { const j = i + d; if (j < 0 || j >= this.steps.length) return; [this.steps[i], this.steps[j]] = [this.steps[j], this.steps[i]]; this.active = j; } }">
        @csrf @method('PUT')
        <x-admin.page-head eyebrow="AI fitting" title="REFRA VisionMatch"><button class="btn btn-sm">Publish pathway</button></x-admin.page-head>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-admin.kpi label="Sessions (30d)" :value="number_format($kpis['sessions'])" />
            <x-admin.kpi label="Match → bag conversion" :value="$kpis['conversion'].'%'" />
            <x-admin.kpi label="Avg top match score" :value="$kpis['avg_score'] ? $kpis['avg_score'].'%' : '—'" />
            <x-admin.kpi label="Most matched frame" :value="$kpis['top_match']" />
        </div>
        <div class="grid gap-6 xl:grid-cols-[1.3fr_1fr]">
            <x-admin.panel title="Questionnaire builder — linear pathway">
                <x-slot:action><button type="button" @click="steps.push({ id: null, key: '', title: 'New step', options: 'Option A · Option B', is_required: false }); active = steps.length - 1" class="btn-ghost btn-sm">+ Step</button></x-slot:action>
                <div class="space-y-2">
                    <template x-for="(s, i) in steps" :key="i">
                        <div @click="active = i" :class="active === i ? 'border-primary bg-secondary' : 'border-border'" class="flex cursor-pointer items-center gap-3 rounded-lg border p-3">
                            <input type="hidden" :name="`steps[${i}][id]`" :value="s.id ?? ''">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-primary font-mono text-[0.65rem] text-primary-foreground" x-text="i + 1"></span>
                            <div class="min-w-0 flex-1 space-y-1">
                                <input :name="`steps[${i}][title]`" x-model="s.title" class="w-full bg-transparent text-sm font-medium outline-none" aria-label="Step title">
                                <input :name="`steps[${i}][options]`" x-model="s.options" class="w-full bg-transparent text-xs text-muted-foreground outline-none" aria-label="Options separated by ·">
                                <input :name="`steps[${i}][key]`" x-model="s.key" placeholder="key (fit, material, shape, finish…)" class="w-full bg-transparent font-mono text-[0.65rem] text-muted-foreground outline-none" aria-label="Step key">
                            </div>
                            <label class="flex items-center gap-1 text-[0.65rem]">Required
                                <input type="hidden" :name="`steps[${i}][is_required]`" :value="s.is_required ? 1 : 0">
                                <span class="switch"><input type="checkbox" x-model="s.is_required"></span>
                            </label>
                            <button type="button" @click.stop="move(i, -1)" aria-label="Move up"><x-lucide-arrow-up class="h-3.5 w-3.5" /></button>
                            <button type="button" @click.stop="move(i, 1)" aria-label="Move down"><x-lucide-arrow-down class="h-3.5 w-3.5" /></button>
                            <button type="button" @click.stop="steps.splice(i, 1); active = 0" aria-label="Delete"><x-lucide-trash-2 class="h-3.5 w-3.5" /></button>
                        </div>
                    </template>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">Separate options with “·”. The step keyed <code>scan</code> is the photo step; <code>fit</code>, <code>shape</code>, <code>material</code> and <code>finish</code> keys feed the match scoring, read by meaning (e.g. any option naming “square”, “titanium” or a black finish). Options no active frame matches are hidden from shoppers.</p>
                @error('steps')<p class="mt-2 text-xs text-destructive">{{ $message }}</p>@enderror
            </x-admin.panel>
            <div class="space-y-6">
                <x-admin.panel title="Live consultation previewer">
                    <template x-if="steps[active]">
                        <div class="rounded-xl bg-secondary p-5">
                            <p class="eyebrow text-[0.55rem] text-muted-foreground" x-text="`Step ${active + 1} of ${steps.length}` + (steps[active].is_required ? '' : ' · optional')"></p>
                            <h3 class="mt-2 font-semibold" x-text="steps[active].title"></h3>
                            <div class="mt-3 flex flex-wrap gap-2"><template x-for="o in steps[active].options.split('·')"><span class="rounded-lg border border-border bg-card px-3 py-1.5 text-xs" x-text="o.trim()"></span></template></div>
                        </div>
                    </template>
                </x-admin.panel>
                @php($scanned = $faceShapes->sum('count'))
                <x-admin.panel title="Face shapes (30d)">
                    @if ($scanned)
                        <ul class="space-y-2 text-xs">
                            @foreach ($faceShapes as $shape)
                                <li class="grid grid-cols-[5rem_1fr_2.5rem] items-center gap-3">
                                    <span>{{ $shape['label'] }}</span>
                                    <span class="h-1.5 rounded bg-secondary"><span class="block h-full rounded bg-bronze" style="width: {{ round($shape['count'] / $scanned * 100) }}%"></span></span>
                                    <span class="text-right font-mono text-muted-foreground">{{ $shape['count'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="text-xs text-muted-foreground">No scans yet.</p>
                    @endif
                    <p class="mt-3 text-[0.65rem] text-muted-foreground">Estimated in the shopper's browser; only the shape and suggested size are stored.</p>
                </x-admin.panel>
                <x-admin.panel title="Recent match sessions">
                    <ul class="space-y-2 text-xs">
                        @forelse ($recent as $session)
                            <li class="flex justify-between gap-3 border-b border-border pb-2"><span>VM-{{ $session->id }} → {{ $session->topProduct?->name ?? '—' }} · {{ $session->top_score }}% @if ($session->converted)<span class="text-bronze">· added to bag</span>@endif</span><span class="text-muted-foreground">{{ $session->created_at->diffForHumans() }}</span></li>
                        @empty
                            <li class="text-muted-foreground">No sessions yet.</li>
                        @endforelse
                    </ul>
                </x-admin.panel>
            </div>
        </div>
    </form>
</x-layouts.admin>
