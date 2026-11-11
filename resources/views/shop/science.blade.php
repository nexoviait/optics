<x-layouts.app title="Our Science — Light Engineered to Nanometer Tolerances" description="Inside the REFRA Research Lab: diamond turning, Abbe dispersion matrices, 1.74 index lenses and 9-layer vacuum coatings.">
    <section class="bg-obsidian text-on-dark">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 py-20 lg:grid-cols-2">
            <div>
                <x-site.badge dark>Research Lab & Crystal Optics Division</x-site.badge>
                <h1 class="mt-6 text-4xl font-semibold leading-tight md:text-5xl">Light Engineered to Nanometer Tolerances</h1>
                <p class="mt-5 max-w-lg text-on-dark-muted">From raw monomer to finished lens, every REFRA optic is diamond-turned, vacuum-coated and centered to your craniofacial geometry in our Zürich and Paris cleanrooms.</p>
                <div class="mt-10 grid grid-cols-2 gap-4 md:grid-cols-4">
                    @foreach ([['0.01 D', 'Surface precision'], [$materials->max('abbe') ?? '58', 'Peak Abbe value'], ['420nm', 'Spectral cutoff'], ['9-layer', 'Vacuum deposition']] as [$v, $l])
                        <div class="rounded-xl border border-on-dark/10 p-4"><div class="font-display text-xl font-semibold text-bronze">{{ $v }}</div><div class="mt-1 text-[0.7rem] text-on-dark-muted">{{ $l }}</div></div>
                    @endforeach
                </div>
            </div>
            <img src="{{ asset('images/hero.jpg') }}" alt="Lens refracting light in the REFRA lab" width="1600" height="1008" class="h-full min-h-[320px] w-full rounded-2xl object-cover">
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-5 py-20">
        <x-site.section-head center eyebrow="4-tier methodology" title="The REFRA Optical Protocol" />
        <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
            @foreach ([['diamond', 'Sub-Nanometer Diamond Turning', 'Freeform generators cut each surface with single-crystal diamond tools to sub-nanometer roughness.'], ['gauge', 'Abbe Value Maximization · Index 1.74', 'Monomer blends tuned to keep chromatic dispersion low even at ultra-thin high indices.'], ['scan-face', 'Biometric Craniofacial Focal Centration', 'VisionMatch™ landmarks set optical centers to your exact pupils, wrap and pantoscopic tilt.'], ['layers', 'Spectral Filter & AR Vacuum Deposition', '9 nano-layers deposited in vacuum: AR, 420nm blue cutoff, hydrophobic and oleophobic top coats.']] as $i => [$icon, $t, $d])
                <div class="rounded-2xl border border-border bg-card p-6">
                    <div class="flex justify-between">@svg('lucide-'.$icon, 'h-6 w-6')<span class="font-mono text-xs text-muted-foreground">TIER 0{{ $i + 1 }}</span></div>
                    <h3 class="mt-6 font-semibold">{{ $t }}</h3><p class="mt-2 text-sm text-muted-foreground">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section id="materials" class="bg-secondary py-20" x-data="{ hi: {{ $materials->sortByDesc('refractive_index')->first()?->id ?? 0 }} }">
        <div class="mx-auto max-w-6xl px-5">
            <x-site.section-head eyebrow="Interactive comparison" title="Abbe Dispersion & Lens Matrix" sub="Live from our lens lab inventory. Select a substrate to highlight it. Higher Abbe means less color fringing; higher index means thinner lenses." />
            <div class="mt-8 overflow-x-auto rounded-2xl border border-border bg-card">
                <table class="w-full min-w-[640px] text-sm">
                    <thead class="bg-secondary text-left text-xs text-muted-foreground"><tr>@foreach (['Substrate', 'Refractive index', 'Abbe value', 'Relative thickness', 'Geometry', 'Coatings'] as $h)<th class="px-4 py-3 font-medium">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach ($materials as $m)
                            {{-- Relative edge thickness vs. a 1.50 index lens scales with 0.5 / (n - 1). --}}
                            @php($thickness = round(0.5 / max(0.01, (float) $m->refractive_index - 1) * 100))
                            <tr @click="hi = {{ $m->id }}" :class="hi === {{ $m->id }} && 'bg-bronze-soft'" class="cursor-pointer border-t border-border transition">
                                <td class="px-4 py-4 font-semibold">{{ $m->name }}</td>
                                <td class="px-4 font-mono">{{ $m->refractive_index }}</td>
                                <td class="px-4 font-mono">{{ $m->abbe }}</td>
                                <td class="px-4"><div class="h-2 w-28 rounded bg-muted"><div class="h-full rounded bg-primary" style="width: {{ min(100, $thickness) }}%"></div></div></td>
                                <td class="px-4">{{ $m->geometry }}</td>
                                <td class="px-4 text-xs">{{ implode(' + ', $m->coatings ?? []) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="mx-auto grid max-w-7xl gap-10 px-5 py-20 lg:grid-cols-2">
        <div>
            <x-site.section-head eyebrow="Registered patents" title="Protected optical innovation" />
            <ul class="mt-6 space-y-3 text-sm">
                @foreach (['EP 3 214 887 — Prismatic binocular alignment surfacing', 'US 11,402,119 — Biometric focal centration from monocular images', 'CH 718 225 — 9-layer spectral vacuum deposition stack'] as $p)
                    <li class="flex gap-2 rounded-lg border border-border bg-card p-3"><x-lucide-badge-check class="h-4 w-4 shrink-0 text-bronze" />{{ $p }}</li>
                @endforeach
            </ul>
        </div>
        <div>
            <x-site.section-head eyebrow="Clinical validation" title="Endorsed by practitioners" />
            <div class="mt-6 space-y-4">
                @foreach ([['Prism-aligned lenses reduced reported digital eye strain in 84% of our trial participants.', 'Dr. Léa Fournier, Ophthalmologist, Paris'], ['The centration accuracy rivals in-clinic pupilometry.', 'Prof. Martin Keller, Optometry, Zürich']] as [$q, $a])
                    <figure class="rounded-xl border border-border bg-card p-5"><blockquote class="font-serif text-lg italic">“{{ $q }}”</blockquote><figcaption class="mt-2 text-xs text-muted-foreground">— {{ $a }}</figcaption></figure>
                @endforeach
            </div>
        </div>
    </section>

    <section id="consultation" class="mx-auto max-w-7xl px-5 pb-20"
             x-data="{ date: '', name: '', email: '', busy: false, async book() {
                if (!this.date) return toast('Pick a preferred date', 'error');
                this.busy = true;
                try { const r = await send('{{ route('requests.consultation') }}', { preferred_date: this.date, name: this.name, email: this.email }); toast(r.message); this.date = ''; }
                catch (e) { toast(e.message, 'error'); }
                this.busy = false; } }">
        <div class="flex flex-col gap-6 rounded-3xl bg-obsidian p-10 text-on-dark md:flex-row md:items-center">
            <x-lucide-video class="h-10 w-10 shrink-0 text-bronze" />
            <div class="flex-1"><h2 class="text-2xl font-medium">1-on-1 Virtual Consultation with a Master Optician</h2><p class="mt-2 text-sm text-on-dark-muted">30 minutes, encrypted video. Progressive, astigmatism and high-index guidance.</p></div>
            <div class="flex flex-wrap gap-2">
                @guest
                    <input x-model="name" placeholder="Name" aria-label="Name" class="w-32 rounded-md border border-on-dark/20 bg-obsidian-soft px-3 py-2 text-sm text-on-dark">
                    <input x-model="email" type="email" placeholder="Email" aria-label="Email" class="w-44 rounded-md border border-on-dark/20 bg-obsidian-soft px-3 py-2 text-sm text-on-dark">
                @endguest
                <input type="date" x-model="date" min="{{ now()->toDateString() }}" class="rounded-md border border-on-dark/20 bg-obsidian-soft px-3 py-2 text-sm text-on-dark" aria-label="Preferred date">
                <button type="button" @click="book" :disabled="busy" class="rounded-md bg-bronze px-5 py-2 text-sm font-medium text-obsidian disabled:opacity-60">Book Session</button>
            </div>
        </div>
    </section>
    <x-site.newsletter :section="$newsletter" />
</x-layouts.app>
