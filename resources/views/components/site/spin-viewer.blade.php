{{--
    360° viewer. Plays the product's turntable photo sequence when one has been
    uploaded; otherwise renders a live 3D model of the frame on a canvas for a
    perfectly smooth spin. Auto-rotates, drag to spin, arrow keys to step.
--}}
@props([
    'product',
    'autoplay' => true,
    'hoverOnly' => false,
    'seconds' => 40,
    'startAngle' => 30,
    'controls' => true,
    'touchTilt' => false, // let vertical swipes tilt the model instead of scrolling the page

    'imgClass' => 'aspect-[4/3] w-full object-cover',
])
@php
    $frames = $product->spinUrls();
    $model = count($frames) > 1 ? null : $product->spinModel();
@endphp
<div x-data="spinViewer(@js($frames), @js($model), @js(['autoplay' => $autoplay, 'hoverOnly' => $hoverOnly, 'seconds' => $seconds, 'startAngle' => $startAngle]))"
     @mouseenter="enter" @mouseleave="leave"
     @keydown.arrow-left.prevent="step(-1)" @keydown.arrow-right.prevent="step(1)" @keydown.space.prevent="toggle"
     tabindex="0" role="img" aria-roledescription="360 degree viewer"
     aria-label="{{ $product->name }} — 360° view. Drag or use the arrow keys to rotate."
     {{ $attributes->class('relative bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-ring') }}>
    <div @pointerdown="pointerDown($event)" @click.capture="clickCapture($event)" @dragstart.prevent
         :class="dragging ? 'cursor-grabbing' : 'cursor-grab'" class="{{ $touchTilt && $model ? 'touch-none' : 'touch-pan-y' }} select-none">
        @if (count($frames) > 1)
            <img src="{{ $frames[0] }}" :src="src" alt="" draggable="false" class="{{ $imgClass }}">
        @else
            <canvas x-ref="canvas" class="block {{ $imgClass }}" aria-hidden="true"></canvas>
        @endif
    </div>

    @if (count($frames) > 1)
        <div x-show="!ready && (preloading || !hoverOnly)" class="pointer-events-none absolute inset-x-0 bottom-0 h-0.5 bg-border">
            <div class="h-full bg-bronze transition-all" :style="{ width: (loaded / frames.length * 100) + '%' }"></div>
        </div>
    @endif

    @if ($controls)
        <div class="absolute bottom-3 left-3 flex items-center gap-1 rounded-full border border-border bg-card/95 px-1.5 py-1 text-xs shadow-sm backdrop-blur" @pointerdown.stop @click.stop.prevent>
            <button type="button" @click="step(-1)" class="rounded-full p-1 hover:bg-secondary" aria-label="Rotate left"><x-lucide-chevron-left class="h-3.5 w-3.5" /></button>
            <button type="button" @click="toggle" class="rounded-full p-1 hover:bg-secondary" :aria-label="playing ? 'Pause rotation' : 'Play rotation'">
                <x-lucide-pause x-show="playing" class="h-3.5 w-3.5" /><x-lucide-play x-show="!playing" class="h-3.5 w-3.5" />
            </button>
            <button type="button" @click="step(1)" class="rounded-full p-1 hover:bg-secondary" aria-label="Rotate right"><x-lucide-chevron-right class="h-3.5 w-3.5" /></button>
            <span class="flex items-center gap-1 pl-1 pr-1.5 font-mono text-[0.6rem] text-muted-foreground"><x-lucide-rotate-cw class="h-3 w-3" /><span x-text="degrees + '°'">{{ $startAngle }}°</span></span>
        </div>
    @endif
</div>
