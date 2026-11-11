<x-layouts.app title="Find My Frames — Face Scan & Frame Matching" description="Scan your face in the browser to estimate your face shape, frame size and PD, then get frames matched to you and try them on in 3D.">
    @viteReactRefresh
    <div class="mx-auto max-w-7xl px-5 py-12"
         x-data="visionMatch(@js(['steps' => $steps, 'faceShapes' => $faceShapes, 'sizes' => $sizes, 'finishColors' => $finishColors, 'matchesUrl' => route('visionmatch.matches'), 'sample' => asset('images/face.jpg'), 'mediapipe' => asset('vendor/mediapipe')]))">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><x-site.badge>REFRA VisionMatch™ · Find My Frames</x-site.badge><h1 class="mt-4 text-3xl font-medium" x-text="title">Face Scan</h1></div>
            <ol class="flex gap-2 text-xs" aria-label="Progress">
                @foreach (['Scan', 'Curate', 'Matches'] as $i => $label)
                    <li :class="step === {{ $i + 1 }} ? 'border-primary bg-primary text-primary-foreground' : (step > {{ $i + 1 }} ? 'border-sage bg-sage-soft' : 'border-border')" :aria-current="step === {{ $i + 1 }} ? 'step' : null" class="flex items-center gap-2 rounded-full border px-3 py-1.5">
                        <x-lucide-check x-show="step > {{ $i + 1 }}" class="h-3 w-3" /><span x-show="step <= {{ $i + 1 }}" class="font-mono">{{ $i + 1 }}</span>{{ $label }}
                    </li>
                @endforeach
            </ol>
        </div>

        {{-- Step 1: scan --}}
        <div x-show="step === 1" class="mt-10 grid gap-8 lg:grid-cols-[1.3fr_1fr]">
            <div class="relative aspect-[4/5] overflow-hidden rounded-2xl bg-obsidian sm:aspect-[4/3]">
                <video x-ref="video" x-show="stream" x-cloak autoplay playsinline muted class="h-full w-full -scale-x-100 object-cover"></video>
                <img x-ref="photo" x-show="!stream && img" x-cloak :src="img" alt="Your portrait" class="h-full w-full object-cover">
                {{-- What was measured, drawn on the photo (object-cover ≙ preserveAspectRatio slice). --}}
                <svg x-show="points && !stream" x-cloak :view-box.camel="`0 0 ${dims.width} ${dims.height}`" preserveAspectRatio="xMidYMid slice" class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true">
                    <line :x1="points?.faceLength[0].x ?? 0" :y1="points?.faceLength[0].y ?? 0" :x2="points?.faceLength[1].x ?? 0" :y2="points?.faceLength[1].y ?? 0" vector-effect="non-scaling-stroke" stroke="#C9A887" stroke-width="1.5" stroke-dasharray="4 4" />
                    <line :x1="points?.cheeks[0].x ?? 0" :y1="points?.cheeks[0].y ?? 0" :x2="points?.cheeks[1].x ?? 0" :y2="points?.cheeks[1].y ?? 0" vector-effect="non-scaling-stroke" stroke="#C9A887" stroke-width="1.5" stroke-dasharray="4 4" />
                    <line :x1="points?.pupils[0].x ?? 0" :y1="points?.pupils[0].y ?? 0" :x2="points?.pupils[1].x ?? 0" :y2="points?.pupils[1].y ?? 0" vector-effect="non-scaling-stroke" stroke="#8A9A8A" stroke-width="2" />
                    <circle :cx="points?.pupils[0].x ?? 0" :cy="points?.pupils[0].y ?? 0" :r="dims.width / 90" fill="none" vector-effect="non-scaling-stroke" stroke="#8A9A8A" stroke-width="2" />
                    <circle :cx="points?.pupils[1].x ?? 0" :cy="points?.pupils[1].y ?? 0" :r="dims.width / 90" fill="none" vector-effect="non-scaling-stroke" stroke="#8A9A8A" stroke-width="2" />
                </svg>
                {{-- Empty state: shown instead of the guide so the prompt never overlaps it --}}
                <div x-show="!live" class="absolute inset-0 grid place-items-center p-6 text-center text-on-dark-muted">
                    <div>
                        <x-lucide-loader-circle x-show="starting" x-cloak class="mx-auto h-12 w-12 animate-spin" />
                        <x-lucide-scan-face x-show="!starting" class="mx-auto h-12 w-12" />
                        <p class="mt-3 text-sm" x-text="starting ? 'Waiting for camera permission…' : 'Start your camera or upload a front-facing portrait'">Start your camera or upload a front-facing portrait</p>
                    </div>
                </div>
                {{-- Alignment guide for the live camera. --}}
                <div x-show="stream" x-cloak x-transition.opacity class="pointer-events-none absolute inset-0 grid place-items-center">
                    <div class="relative h-[70%] w-[42%] rounded-[50%] border-2 border-bronze shadow-[0_0_0_9999px_rgba(15,17,19,0.35)]">
                        <div class="absolute left-[22%] right-[22%] top-[40%] flex justify-between"><span class="h-3 w-3 rounded-full border border-bronze"></span><span class="h-3 w-3 rounded-full border border-bronze"></span></div>
                        <div class="absolute left-[25%] right-[25%] top-[42%] border-t border-dashed border-bronze"></div>
                    </div>
                </div>
                <div class="absolute inset-x-4 top-4 flex justify-between font-mono text-[0.6rem] text-on-dark">
                    <span class="rounded bg-obsidian/70 px-2 py-1" x-text="'● ' + (stream ? 'LIVE CAMERA' : face ? 'MEASURED' : img ? 'PHOTO READY' : 'AWAITING INPUT')"></span>
                    <span class="rounded bg-obsidian/70 px-2 py-1">ON-DEVICE · NOT UPLOADED</span>
                </div>
                <div x-show="scanning" x-cloak class="absolute inset-x-4 bottom-4 flex items-center gap-2 rounded-lg bg-obsidian/80 p-3 font-mono text-[0.65rem] text-on-dark" role="status" aria-live="polite">
                    <x-lucide-loader-circle class="h-3.5 w-3.5 animate-spin" /><span x-text="scanText"></span>
                </div>
            </div>

            <div class="space-y-4">
                {{-- Results --}}
                <div x-show="face" x-cloak class="rounded-2xl border border-border bg-card p-6" aria-live="polite">
                    <p class="eyebrow text-[0.6rem] text-muted-foreground">Your face profile · estimated</p>
                    <dl class="mt-4 grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-xs text-muted-foreground">Face shape</dt><dd class="mt-0.5 text-lg font-semibold" x-text="shapeLabel(face?.shape)"></dd></div>
                        <div><dt class="text-xs text-muted-foreground">Suggested size</dt><dd class="mt-0.5 text-lg font-semibold" x-text="suggested ? `${suggested.code} · ${suggested.width} mm` : 'M · 138 mm'"></dd></div>
                        <div><dt class="text-xs text-muted-foreground">Face width</dt><dd class="mt-0.5 font-mono" x-text="face ? `≈ ${Math.round(face.width)} mm` : ''"></dd></div>
                        <div><dt class="text-xs text-muted-foreground">Pupil distance (PD)</dt><dd class="mt-0.5 font-mono" x-text="face ? `≈ ${Math.round(face.pd)} mm` : ''"></dd></div>
                    </dl>
                    <p class="mt-4 rounded-lg bg-secondary p-3 text-xs" x-text="shapeWhy(face?.shape)"></p>
                    <p x-show="face && (face.turned || face.small || !face.iris)" class="mt-3 rounded-lg bg-bronze-soft p-3 text-xs" x-text="scanWarning()"></p>
                    <div class="mt-5 grid gap-2">
                        <button type="button" @click="next()" class="btn py-3">Continue <x-lucide-arrow-right class="h-4 w-4" /></button>
                        <button type="button" @click="resetScan()" class="text-xs text-muted-foreground underline hover:text-foreground">Scan a different photo</button>
                    </div>
                    <p class="mt-3 text-[0.65rem] text-muted-foreground">Estimates from your photo. For prescription lenses, your optician's PD measurement takes precedence.</p>
                </div>

                {{-- Inputs --}}
                <div x-show="!face" class="rounded-2xl border border-border bg-card p-6">
                    <h2 class="font-semibold">Align your face within the oval</h2>
                    <ul class="mt-3 space-y-1.5 text-sm text-muted-foreground"><li>• Look straight ahead, remove glasses</li><li>• Even lighting, no harsh shadows</li><li>• Keep your whole face in the frame</li></ul>
                    <p x-show="scanError" x-cloak role="alert" class="mt-4 rounded-lg bg-bronze-soft p-3 text-xs" x-text="scanError === 'no-face' ? 'We couldn’t find a face in that photo. Try a clear, front-facing portrait with your whole face visible.' : 'Face scanning isn’t available in this browser. You can still choose your face shape and size in the next step.'"></p>
                    <div class="mt-6 grid gap-2">
                        <button type="button" @click="stream ? stopCam() : startCam()" :disabled="starting || scanning" class="btn-ghost py-2.5 disabled:cursor-not-allowed disabled:opacity-60">
                            <x-lucide-camera-off x-show="stream" x-cloak class="h-4 w-4" /><x-lucide-camera x-show="!stream" class="h-4 w-4" />
                            <span x-text="starting ? 'Starting camera…' : stream ? 'Stop Camera' : 'Start Live Camera'">Start Live Camera</span>
                        </button>
                        <button type="button" @click="$refs.file.click()" :disabled="scanning" class="btn-ghost py-2.5 disabled:cursor-not-allowed disabled:opacity-60">
                            <x-lucide-upload class="h-4 w-4" /> <span x-text="img && !stream ? 'Choose a Different Photo' : 'Upload Portrait Photo'">Upload Portrait Photo</span>
                        </button>
                        <input x-ref="file" type="file" accept="image/*" class="hidden" tabindex="-1" aria-hidden="true" @change="upload($event)">
                        <button type="button" @click="sample" :disabled="scanning" class="justify-self-center text-xs text-muted-foreground underline hover:text-foreground disabled:opacity-60">Use sample portrait</button>
                        <button type="button" @click="capture" :disabled="scanning || starting || !live" class="btn mt-2 py-3 disabled:cursor-not-allowed disabled:opacity-50">
                            <x-lucide-loader-circle x-show="scanning" x-cloak class="h-4 w-4 animate-spin" />
                            <span x-text="scanning ? 'Scanning…' : stream ? 'Capture & Scan' : 'Scan My Face'">Scan My Face</span>
                            <x-lucide-arrow-right x-show="!scanning" class="h-4 w-4" />
                        </button>
                        <p x-show="!live" class="text-center text-xs text-muted-foreground">Add a portrait above to enable the scan.</p>
                        <button type="button" x-show="scanError === 'unavailable'" x-cloak @click="next()" class="text-xs underline">Continue without a scan</button>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">Your photo is analysed in your browser and never uploaded. Only your face shape and suggested size are used to rank frames.</p>
            </div>
        </div>

        {{-- Step 2: curate --}}
        <div x-show="step === 2" x-cloak class="mt-10 space-y-10">
            <section>
                <h2 class="eyebrow text-muted-foreground">Your face shape</h2>
                <div class="mt-3 grid grid-cols-3 gap-3 md:grid-cols-6" role="group" aria-label="Face shape">
                    <template x-for="s in faceShapes" :key="s.key">
                        <button type="button" @click="faceShape = s.key" :aria-pressed="faceShape === s.key" :class="faceShape === s.key ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:border-bronze'" class="rounded-xl border p-3 text-left text-sm transition">
                            <span class="font-semibold" x-text="s.label"></span>
                            <span x-show="face?.shape === s.key" class="mt-1 block text-[0.65rem] opacity-80">Detected</span>
                        </button>
                    </template>
                </div>
                <p class="mt-2 text-xs text-muted-foreground" x-text="faceShape ? shapeWhy(faceShape) : 'Not sure? Pick the closest, or skip — we’ll match on your answers alone.'"></p>
            </section>
            <section>
                <h2 class="eyebrow text-muted-foreground">Frame size</h2>
                <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4" role="group" aria-label="Frame size">
                    <template x-for="s in sizes" :key="s.code">
                        <button type="button" @click="size = s.code" :aria-pressed="size === s.code" :class="size === s.code ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:border-bronze'" class="rounded-xl border p-3 text-left text-sm transition">
                            <span class="font-semibold" x-text="s.code"></span> <span class="font-mono text-xs opacity-80" x-text="`${s.lens}-${s.bridge}-${s.temple}`"></span>
                            <span class="mt-1 block text-[0.65rem] opacity-80" x-text="suggested?.code === s.code ? `Suggested · ${s.width} mm` : `${s.width} mm wide`"></span>
                        </button>
                    </template>
                </div>
            </section>
            <p class="rounded-lg bg-bronze-soft p-4 text-sm">The questions below are optional — skip them and we'll match on your face alone.</p>
            <template x-for="s in steps" :key="s.key">
                <section>
                    <h2 class="eyebrow text-muted-foreground"><span x-text="s.title"></span> <span x-show="!s.required" class="normal-case tracking-normal">· optional</span></h2>
                    <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
                        <template x-for="o in s.options" :key="o.label">
                            <button type="button" @click="toggle(s.key, o.label)" :aria-pressed="prefs[s.key] === o.label" :class="prefs[s.key] === o.label ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card hover:border-bronze'" class="rounded-xl border p-4 text-left text-sm transition">
                                <span x-show="o.color" class="mr-2 inline-block h-3 w-3 rounded-full align-middle" :style="{ background: o.color }"></span>
                                <span class="font-semibold" x-text="o.label"></span>
                                <span x-show="suitsFace(o)" class="mt-2 flex items-center gap-1 text-[0.65rem] opacity-80"><x-lucide-sparkles class="h-3 w-3" />Suits your face</span>
                            </button>
                        </template>
                    </div>
                </section>
            </template>
            <div class="flex flex-wrap justify-between gap-3">
                <button type="button" @click="step = 1" class="btn-ghost px-5 py-3"><x-lucide-arrow-left class="h-4 w-4" /> Back</button>
                <div class="flex flex-wrap gap-3">
                    <button type="button" @click="reveal(true)" :disabled="loading" class="btn-ghost px-5 py-3">Skip questions</button>
                    <button type="button" @click="reveal()" :disabled="loading" class="btn px-5 py-3"><x-lucide-loader-circle x-show="loading" x-cloak class="h-4 w-4 animate-spin" />Reveal My Matches <x-lucide-arrow-right class="h-4 w-4" /></button>
                </div>
            </div>
        </div>

        {{-- Step 3: results --}}
        <div x-show="step === 3" x-cloak class="mt-10 grid gap-8 lg:grid-cols-[1.1fr_1fr]">
            <div id="vm-try-on" class="scroll-mt-20 self-start lg:sticky lg:top-24">
                {{-- The 3D try-on (React), on your own photo at the chosen size. --}}
                <div x-ref="tryon" x-effect="renderTryOn()" class="min-h-64"></div>
                <div x-show="selected" class="mt-4 flex flex-wrap items-center gap-3">
                    <span class="eyebrow text-[0.6rem] text-muted-foreground">Frame finish</span>
                    <template x-for="color in selected?.finishes ?? []" :key="color">
                        <button type="button" :title="finishName(color)" :aria-label="'Finish: ' + finishName(color)" :aria-pressed="finish === color" @click="finish = color" :class="finish === color ? 'ring-2 ring-primary ring-offset-2 ring-offset-background' : 'hover:scale-110'" class="h-7 w-7 rounded-full border border-border transition" :style="{ background: color }"></button>
                    </template>
                    <span class="text-xs text-muted-foreground" x-text="selected ? finishName(finish) : ''"></span>
                </div>
            </div>
            <div>
                <template x-if="selected">
                    <div class="rounded-2xl border border-border bg-card p-6">
                        <div class="flex items-start justify-between gap-4">
                            <div><p class="eyebrow text-[0.55rem] text-muted-foreground" x-text="`${selected.id === matches[0]?.id ? 'Top match' : 'Trying on'} • ${selected.score}% fit`"></p><h2 class="mt-1 text-2xl font-semibold" x-text="selected.name"></h2><p class="text-sm text-muted-foreground" x-text="`${selected.shape} · ${selected.material}`"></p></div>
                            <div class="font-display text-xl font-semibold" x-text="'$' + selected.price"></div>
                        </div>
                        <ul x-show="selected.reasons.length" class="mt-4 space-y-1.5 text-sm">
                            <template x-for="reason in selected.reasons" :key="reason">
                                <li class="flex items-start gap-2"><x-lucide-check class="mt-0.5 h-4 w-4 shrink-0 text-sage" /><span x-text="reason"></span></li>
                            </template>
                        </ul>
                        <div class="mt-5 flex flex-wrap items-center gap-2 text-xs" role="group" aria-label="Frame size">
                            <span class="text-muted-foreground">Size</span>
                            <template x-for="s in sizes" :key="s.code">
                                <button type="button" @click="size = s.code" :aria-pressed="size === s.code" :class="size === s.code ? 'border-primary bg-primary text-primary-foreground' : 'border-border hover:border-foreground'" class="min-w-9 rounded-full border px-2.5 py-1" x-text="s.code"></button>
                            </template>
                            <span x-show="suggested" class="text-muted-foreground" x-text="`Suggested ${suggested?.code}`"></span>
                        </div>
                        <div class="mt-5 flex gap-2">
                            <button type="button" @click="addToBag(selected.id, selected.color, size)" class="btn flex-1 py-3"><x-lucide-shopping-bag class="h-4 w-4" /> Add to Bag</button>
                            <a :href="`${selected.url}?size=${encodeURIComponent(size ?? '')}`" class="btn-ghost py-3">Customize Lenses</a>
                        </div>
                    </div>
                </template>
                <h2 class="eyebrow mt-8 flex items-center gap-1.5 text-muted-foreground"><x-lucide-sparkles class="h-3 w-3" /><span x-text="faceShape ? `Curated for your ${shapeLabel(faceShape).toLowerCase()} face` : 'Curated for you'"></span><span x-show="unique"> · unique picks</span></h2>
                <div class="mt-3 grid grid-cols-2 gap-3">
                    <template x-for="p in matches" :key="p.id">
                        <button type="button" @click="select(p)" :aria-pressed="selected?.id === p.id" :class="selected?.id === p.id ? 'border-primary ring-1 ring-primary' : 'border-border hover:border-foreground/30'" class="relative rounded-xl border bg-card p-2 text-left transition">
                            <span x-show="selected?.id === p.id" class="eyebrow absolute left-3 top-3 z-10 flex items-center gap-1 rounded-full bg-primary px-2 py-0.5 text-[0.5rem] text-primary-foreground"><x-lucide-scan-face class="h-2.5 w-2.5" /> Trying on</span>
                            <img :src="p.image" :alt="p.name" loading="lazy" class="aspect-[4/3] w-full rounded-lg object-cover">
                            <div class="mt-2 flex justify-between px-1 text-xs"><span class="font-medium" x-text="p.name"></span><span class="font-mono text-bronze" x-text="p.score + '%'"></span></div>
                        </button>
                    </template>
                </div>
                <button type="button" @click="restart" class="mt-6 flex items-center gap-2 text-xs text-muted-foreground"><x-lucide-refresh-cw class="h-3 w-3" /> Start over</button>
            </div>
        </div>
    </div>
</x-layouts.app>
