@props(['s'])
@php
    $tones = [
        'New' => 'bg-bronze-soft', 'Processing' => 'bg-secondary', 'Ready to Ship' => 'bg-sage-soft', 'Shipped' => 'bg-sage-soft',
        'Delivered' => 'bg-primary text-primary-foreground', 'Cancelled' => 'bg-destructive text-destructive-foreground',
        'Active' => 'bg-sage-soft', 'active' => 'bg-sage-soft', 'Live' => 'bg-sage-soft', 'Draft' => 'bg-secondary', 'draft' => 'bg-secondary',
        'Succeeded' => 'bg-sage-soft', 'Pending' => 'bg-bronze-soft', 'Refunded' => 'bg-secondary', 'Failed' => 'bg-destructive text-destructive-foreground',
        'Low' => 'bg-destructive text-destructive-foreground', 'OK' => 'bg-sage-soft',
        'Scheduled' => 'bg-sage-soft', 'Completed' => 'bg-primary text-primary-foreground',
        'Obsidian Circle' => 'bg-primary text-primary-foreground', 'Champagne Circle' => 'bg-bronze-soft', 'Sage Member' => 'bg-sage-soft',
    ];
@endphp
<span class="eyebrow inline-block whitespace-nowrap rounded-full px-2.5 py-1 text-[0.55rem] {{ $tones[$s] ?? 'bg-secondary' }}">{{ ucfirst($s) }}</span>
