{{-- Server-rendered email preview with sample merge-token values. --}}
@php($fill = fn ($t) => \App\Models\EmailTemplate::fillTokens($t))
<div class="overflow-hidden rounded-xl border border-border bg-card">
    <div class="border-b border-border bg-secondary px-4 py-2 text-xs"><span class="text-muted-foreground">Subject: </span><span class="font-medium">{{ $fill($subject) }}</span></div>
    <div class="space-y-3 p-5">
        @foreach ($blocks as $block)
            @switch($block['type'] ?? '')
                @case('Logo')<div class="flex items-center gap-2"><x-site.logo-mark class="h-6 w-6" /><span class="font-display text-sm font-semibold tracking-[0.3em]">REFRA</span></div>@break
                @case('Heading')<h3 class="text-lg font-semibold">{{ $fill($block['text']) }}</h3>@break
                @case('Paragraph')<p class="text-sm text-muted-foreground">{{ $fill($block['text']) }}</p>@break
                @case('CTA Button')<span class="inline-block rounded-md bg-primary px-4 py-2 text-xs text-primary-foreground">{{ $fill($block['text']) }}</span>@break
                @case('Order Summary')<div class="rounded-lg bg-secondary p-3 text-xs"><div class="flex justify-between"><span>Apex 01 Titanium</span><span>$540</span></div><div class="mt-1 flex justify-between font-semibold"><span>Total</span><span>$620</span></div></div>@break
                @case('Lens Specs')<div class="grid grid-cols-3 gap-2 font-mono text-[0.65rem]">@foreach (['OD' => '-2.75', 'OS' => '-3.00', 'Index' => '1.67'] as $k => $v)<div class="rounded bg-secondary p-2">{{ $k }} {{ $v }}</div>@endforeach</div>@break
                @case('Divider')<hr class="border-border">@break
                @case('Image')<div class="h-24 rounded-lg bg-obsidian"></div>@break
                @case('Footer')<p class="border-t border-border pt-3 text-[0.65rem] text-muted-foreground">{{ $fill($block['text']) }}</p>@break
            @endswitch
        @endforeach
    </div>
</div>
