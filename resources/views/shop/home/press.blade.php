<section class="border-t border-border py-20">
    <p class="eyebrow text-center text-muted-foreground">{{ $c['eyebrow'] ?? '' }}</p>
    <div class="mx-auto mt-10 grid max-w-6xl gap-10 px-5 md:grid-cols-3">
        @foreach ($c['quotes'] ?? [] as $quote)
            <figure class="text-center">
                <blockquote class="font-serif text-xl italic leading-snug">“{{ $quote['quote'] ?? '' }}”</blockquote>
                <figcaption class="eyebrow mt-4 text-[0.6rem] text-muted-foreground">— {{ $quote['source'] ?? '' }}</figcaption>
            </figure>
        @endforeach
    </div>
</section>
