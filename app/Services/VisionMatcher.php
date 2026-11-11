<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Find My Frames: ranks catalog frames for an estimated face shape and the
 * shopper's optional preferences, with the reasons behind each match.
 * Preferences are read by meaning (frame-shape family, material, colour
 * family), so they work whatever wording the admin gives the options.
 */
class VisionMatcher
{
    /** Named finish swatches offered by the palette step. */
    public const FINISH_COLORS = [
        'Champagne Bronze' => '#C9A887',
        'Bronze' => '#C9A887',
        'Obsidian' => '#0F1113',
        'Sage' => '#8A9A8A',
        'Raw Titanium' => '#6B7078',
        'Raw Ti' => '#6B7078',
    ];

    public const FACE_SHAPES = [
        'oval' => 'Oval',
        'round' => 'Round',
        'square' => 'Square',
        'heart' => 'Heart',
        'oblong' => 'Oblong',
        'diamond' => 'Diamond',
    ];

    /** Frame shapes that flatter each face shape, and why (standard optician guidance). */
    public const SUITS = [
        'oval' => [['square', 'rectangle', 'aviator', 'geometric', 'cat-eye', 'browline', 'round'], 'Balanced proportions carry this shape well'],
        'round' => [['square', 'rectangle', 'geometric', 'browline', 'navigator'], 'Angular lines add definition to a round face'],
        'square' => [['round', 'oval', 'panto', 'aviator', 'cat-eye'], 'Soft curves balance a strong jaw'],
        'heart' => [['aviator', 'round', 'oval', 'panto', 'rimless'], 'A lighter brow and fuller base balance a wider forehead'],
        'oblong' => [['square', 'aviator', 'round', 'shield', 'oversized', 'panto'], 'A deep frame shortens a longer face'],
        'diamond' => [['cat-eye', 'oval', 'browline', 'rimless', 'round'], 'Soft, upswept brows frame high cheekbones'],
    ];

    /** Frame shapes that repeat, and so exaggerate, a face's own lines. */
    private const AVOID = [
        'oval' => [],
        'round' => ['round', 'oval'],
        'square' => ['square', 'geometric'],
        'heart' => ['cat-eye', 'browline'],
        'oblong' => ['slim'],
        'diamond' => ['rectangle', 'slim'],
    ];

    /** Shape families and the words that name them. */
    private const SHAPE_WORDS = [
        'square' => ['square'],
        'rectangle' => ['rectang'],
        'round' => ['round'],
        'oval' => ['oval'],
        'panto' => ['panto', 'keyhole'],
        'cat-eye' => ['cat'],
        'aviator' => ['aviator', 'pilot', 'navigator'],
        'navigator' => ['navigator'],
        'geometric' => ['geometric', 'hex', 'octagon'],
        'browline' => ['browline', 'clubmaster'],
        'rimless' => ['rimless'],
        'shield' => ['shield', 'flat-top', 'flat top'],
        'oversized' => ['oversized', 'bold'],
        'slim' => ['slim'],
    ];

    public static function finishColor(string $name): string
    {
        return self::FINISH_COLORS[$name] ?? '#6B7078';
    }

    /** @return list<string> */
    public static function shapeFamilies(string $text): array
    {
        $text = Str::lower($text);

        return array_keys(array_filter(self::SHAPE_WORDS, fn (array $words) => Str::contains($text, $words)));
    }

    /** @return list<string> */
    public static function materialFamilies(string $text): array
    {
        $text = Str::lower($text);

        return array_keys(array_filter([
            'titanium' => Str::contains($text, 'titanium') || preg_match('/\bti\b/', $text),
            'acetate' => Str::contains($text, 'acetate'),
            'tr90' => Str::contains($text, ['tr90', 'tr-90']),
            'metal' => Str::contains($text, ['titanium', 'steel', 'metal', 'alloy', 'alumin']),
        ]));
    }

    /** Broad colour family of a hex colour (black, grey, brown, gold, pink, red, green, blue, purple). */
    public static function colourFamily(string $hex): string
    {
        [$r, $g, $b] = array_map(fn (int $i) => hexdec(substr(str_pad(ltrim($hex, '#'), 6, '0'), $i, 2)) / 255, [0, 2, 4]);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;
        $s = $d == 0 ? 0 : $d / (1 - abs(2 * $l - 1));
        $h = match (true) {
            $d == 0 => 0,
            $max === $r => 60 * fmod(($g - $b) / $d + 6, 6),
            $max === $g => 60 * (($b - $r) / $d + 2),
            default => 60 * (($r - $g) / $d + 4),
        };

        return match (true) {
            $l < 0.2 => 'black',
            $s < 0.15 => 'grey',
            $h < 15 || $h >= 330 => $l >= 0.45 ? 'pink' : 'red',
            $h < 45 => $l < 0.45 ? 'brown' : ($l > 0.7 && $s > 0.5 ? 'pink' : 'gold'),
            $h < 70 => 'gold',
            $h < 170 => 'green',
            $h < 260 => 'blue',
            default => 'purple',
        };
    }

    /** @return list<string> shape families of a catalog frame, including its frame type */
    public static function productFamilies(Product $product): array
    {
        return array_values(array_unique([
            ...self::shapeFamilies((string) $product->shape),
            ...match ($product->frame_type) {
                'rimless' => ['rimless'],
                'half_rim' => ['browline'],
                default => [],
            },
        ]));
    }

    /** Gender a fit-profile option asks for, or null. */
    private static function fitGender(string $option): ?string
    {
        $option = Str::lower($option);

        return match (true) {
            Str::contains($option, ['femin', 'women', 'female']) => 'women',
            Str::contains($option, ['mascul', 'men', 'male']) => 'men',
            Str::contains($option, 'unisex') => 'unisex',
            default => null,
        };
    }

    /**
     * Whether any frame in the catalog matches an option, so the questions never dead-end.
     * Options whose meaning isn't recognised are kept.
     *
     * @param  Collection<int, Product>  $products
     */
    public function optionAvailable(string $step, string $option, Collection $products): bool
    {
        return match ($step) {
            'shape' => ($wanted = self::shapeFamilies($option)) === [] || $products->contains(fn (Product $p) => array_intersect($wanted, self::productFamilies($p)) !== []),
            'material' => ($wanted = self::materialFamilies($option)) === [] || $products->contains(fn (Product $p) => in_array($wanted[0], self::materialFamilies((string) $p->material), true)),
            'finish' => $products->contains(fn (Product $p) => in_array(self::colourFamily(self::finishColor($option)), array_map(self::colourFamily(...), $p->finishColors()), true)),
            'fit' => ($gender = self::fitGender($option)) === null || $products->contains(fn (Product $p) => in_array($p->gender, [$gender, 'unisex'], true)),
            default => true,
        };
    }

    /**
     * How well a frame suits the face shape and preferences, out of 99, and why.
     *
     * @param  array<string, ?string>  $prefs
     * @return array{score: int, reasons: list<string>}
     */
    public function assess(Product $product, array $prefs, ?string $faceShape = null): array
    {
        $families = self::productFamilies($product);
        $score = 60;
        $reasons = [];

        if ($faceShape !== null && isset(self::SUITS[$faceShape])) {
            [$suits, $why] = self::SUITS[$faceShape];
            if (array_intersect($families, $suits) !== []) {
                $score += 24;
                $reasons[] = $why;
            } elseif (array_intersect($families, self::AVOID[$faceShape]) !== []) {
                $score -= 6;
            } else {
                $score += 8;
            }
        } else {
            $score += 8;
        }

        if (! empty($prefs['shape']) && array_intersect($families, self::shapeFamilies($prefs['shape'])) !== []) {
            $score += 10;
            $reasons[] = 'The '.Str::lower($prefs['shape']).' look you chose';
        }

        $material = ! empty($prefs['material']) ? (self::materialFamilies($prefs['material'])[0] ?? null) : null;
        if ($material !== null && in_array($material, self::materialFamilies((string) $product->material), true)) {
            $score += 5;
            $reasons[] = "In {$product->material}, as you asked";
        }

        if (! empty($prefs['finish']) && in_array(self::colourFamily(self::finishColor($prefs['finish'])), array_map(self::colourFamily(...), $product->finishColors()), true)) {
            $score += 4;
            $reasons[] = "Close to the {$prefs['finish']} finish you picked";
        }

        $gender = ! empty($prefs['fit']) ? self::fitGender($prefs['fit']) : null;
        if ($gender !== null && in_array($product->gender, [$gender, 'unisex'], true)) {
            $score += 3;
        }

        return ['score' => max(40, min(99, $score)), 'reasons' => array_slice($reasons, 0, 3)];
    }

    /** @param array<string, ?string> $prefs */
    public function score(Product $product, array $prefs, ?string $faceShape = null): int
    {
        return $this->assess($product, $prefs, $faceShape)['score'];
    }

    /**
     * Best frames first; equal scores go to the more viewed frame.
     *
     * @param  array<string, ?string>  $prefs
     * @return Collection<int, array{product: Product, score: int, reasons: list<string>}>
     */
    public function rank(array $prefs, ?string $faceShape = null, int $limit = 6): Collection
    {
        return Product::query()->active()->ordered()->get()
            ->map(fn (Product $p) => ['product' => $p] + $this->assess($p, $prefs, $faceShape))
            ->sortBy([fn (array $a, array $b) => $b['score'] <=> $a['score'], fn (array $a, array $b) => $b['product']->views <=> $a['product']->views])
            ->take($limit)
            ->values();
    }
}
