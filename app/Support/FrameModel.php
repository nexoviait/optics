<?php

namespace App\Support;

use App\Models\Product;
use Illuminate\Support\Str;

/**
 * A 3D description of an eyeglass frame built from the product's catalogue data:
 * lens outlines after its shape name at real proportions (lens, bridge and temple
 * in mm), and its look, read from the material / colour text: acetate, metal,
 * rimless or half-rim; solid, crystal, gradient or tortoise finish; temple
 * material and tips; lens tint.
 *
 * `lines` (rims, bridge, end pieces, temples) drive the canvas spin on product
 * cards and the live try-on; `style` adds what the solid 3D model needs.
 *
 * Coordinates are millimetres: x to the right, y up, z towards the viewer.
 */
class FrameModel
{
    private const LENS_POINTS = 40;

    private const SILVER = '#C3C7CC';

    /** Metal colour words, most specific first. */
    private const METAL_COLORS = [
        'rose gold' => '#C9927E',
        'gunmetal' => '#54575C',
        'champagne' => '#CDB48A',
        'gold' => '#C9A96E',
        'bronze' => '#8C6A4A',
        'black' => '#1C1C1E',
        'silver' => self::SILVER,
        'chrome' => self::SILVER,
    ];

    private const ACETATE_COLORS = [
        'burgundy' => '#5E1A26',
        'smoke' => '#5A524C',
        'horn' => '#5A524C',
        'black' => '#141414',
        'tortoise' => '#6B3F1F',
        'havana' => '#6B3F1F',
    ];

    private const LENS_TINTS = [
        'green' => '#3B5A3A',
        'g-15' => '#3B5A3A',
        'brown' => '#5B3A22',
        'amber' => '#5B3A22',
        'bronze' => '#5B3A22',
        'blue' => '#2C5C8F',
        'azure' => '#2C5C8F',
        'rose' => '#8A4B57',
        'pink' => '#8A4B57',
        'yellow' => '#8D6C25',
    ];

    /**
     * @return array{color: string, tinted: bool, lines: list<array<string, mixed>>, style: array<string, mixed>}
     */
    public static function for(Product $product): array
    {
        $shape = Str::lower((string) $product->shape);
        $finishes = array_values($product->finishColors());
        $color = $finishes[0] ?? '#0F1113';

        // "Ember Tortoise with Smoke Gradient Lenses": the frame, then the lenses.
        [$frameColor, $lensColor] = array_pad(preg_split('/\s+with\s+/i', (string) $product->color, 2), 2, '');
        $material = Str::lower((string) $product->material);
        $finishText = Str::lower($product->material.' '.$product->color_name);
        $look = Str::lower($product->material.' '.$product->color_name.' '.$frameColor);
        $templeText = Str::lower((string) $product->temple_material);
        $lensText = Str::lower($lensColor.' '.$product->lens_tag);

        $spec = self::lensSpec($shape);
        $rim = match (true) {
            $product->isRimless() => 'rimless',
            $product->frame_type === 'half_rim' || Str::contains($shape, ['browline', 'clubmaster', 'semi']) => 'half',
            self::isMetal($material) => 'metal',
            default => 'acetate',
        };
        $metalFront = in_array($rim, ['metal', 'rimless'], true);
        $finish = $metalFront ? 'solid' : match (true) {
            Str::contains($finishText, ['tortoise', 'havana', 'demi']) => 'tortoise',
            Str::contains($finishText, ['gradient', 'fade', 'ombre', 'ombré']) => 'gradient',
            Str::contains($finishText, ['crystal', 'clear', 'translucent', 'transparent']) => 'crystal',
            default => 'solid',
        };
        $roughness = match (true) {
            Str::contains($look, 'matte') => $metalFront ? 0.45 : 0.62,
            Str::contains($look, 'brushed') => 0.38,
            Str::contains($look, ['polished', 'gloss', 'shiny']) => $metalFront ? 0.16 : 0.12,
            default => $metalFront ? 0.24 : 0.22,
        };
        $thickness = match ($rim) {
            'metal' => 2.2,
            'rimless' => 1.5,
            'half' => 1.4,
            default => Str::contains($shape, ['bold', 'oversized', 'chunky', 'thick']) ? 6.4 : (Str::contains($shape, ['slim', 'thin']) ? 3.8 : 4.8),
        };
        $brow = Str::contains($shape, ['bold', 'oversized']) ? 6.4 : 5.2;
        $wire = $rim === 'half' ? (self::named($look, self::METAL_COLORS) ?? self::SILVER) : $color;
        $temple = self::temple($templeText, $rim, $color, $finish, $finishes);
        $frontColor = $rim === 'half' ? $wire : $color;

        $lines = [];
        $lenses = [];
        foreach ([-1, 1] as $side) {
            $lenses[$side] = self::lens($side * ($spec['bridge'] / 2 + $spec['w'] / 2), $side, $spec);
            $lines[] = ['part' => 'rim', 'points' => $lenses[$side], 'width' => $thickness, 'closed' => true, 'lens' => true, 'color' => $frontColor];
        }
        if ($rim === 'half') {
            foreach ([-1, 1] as $side) {
                $lines[] = ['part' => 'brow', 'points' => self::topRun($lenses[$side], $spec['dy']), 'width' => $brow, 'closed' => false, 'color' => $color];
            }
        }

        // Bridge: a solid band in acetate, a wire arch otherwise; ends sit inside the rims.
        $h = $spec['h'];
        $inner = $spec['bridge'] / 2;
        [$yEnd, $yMid] = match (true) {
            $rim === 'rimless' => [0.10 * $h, 0.26 * $h],
            $rim === 'acetate' && Str::contains($shape, 'keyhole') => [0.04 * $h, 0.34 * $h],
            default => [0.22 * $h, 0.31 * $h],
        };
        $xEnd = $rim === 'acetate' ? $inner - $thickness * 0.5 : $inner - 0.5;
        $yEnd += $spec['dy'];
        $yMid += $spec['dy'];
        $lines[] = ['part' => 'bridge', 'points' => [
            [-$xEnd, $yEnd, 0.4], [-$xEnd * 0.55, $yEnd + ($yMid - $yEnd) * 0.75, 0.9], [0, $yMid, 1.1],
            [$xEnd * 0.55, $yEnd + ($yMid - $yEnd) * 0.75, 0.9], [$xEnd, $yEnd, 0.4],
        ], 'width' => $rim === 'acetate' ? $thickness * 0.9 : 2.0, 'closed' => false, 'color' => $frontColor];

        // Aviators: the top bar of the double bridge, joining the rims 30% in from the nose.
        if ($spec['bar']) {
            $target = $inner + 0.3 * $spec['w'];
            $top = array_filter($lenses[1], fn (array $p) => $p[1] > $spec['dy']);
            usort($top, fn (array $p, array $q) => abs($p[0] - $target) <=> abs($q[0] - $target));
            [$bx, $by] = $top[0];
            $lines[] = ['part' => 'bar', 'points' => [[-$bx, $by, 0.3], [-$bx / 2, $by + 1.5, 0.6], [0, $by + 2, 0.8], [$bx / 2, $by + 1.5, 0.6], [$bx, $by, 0.3]], 'width' => 1.8, 'closed' => false, 'color' => $frontColor];
        }

        // End pieces from the rim's outer edge back to the hinge, then the temples.
        $endY = $spec['dy'] + $h * match ($rim) {
            'rimless' => 0.22, 'half' => 0.34, default => 0.30
        };
        $bold = Str::contains($shape, ['bold', 'oversized']);
        foreach ([-1, 1] as $side) {
            $outer = self::outerX($lenses[$side], $endY, $side);
            [$start, $hinge] = match ($rim) {
                'acetate', 'half' => [$outer + ($rim === 'half' ? $brow : $thickness) * 0.35, $outer + ($rim === 'half' ? $brow : $thickness) + 2.2],
                'rimless' => [$outer - 1.2, $outer + 4.5],
                default => [$outer + 0.6, $outer + 5.5],
            };
            $hingePoint = [$side * $hinge, $endY - 0.6, -3.5];
            $lines[] = ['part' => 'end', 'points' => [[$side * $start, $endY, 0.2], $hingePoint], 'width' => in_array($rim, ['acetate', 'half'], true) ? 4.2 : 2.2, 'closed' => false, 'color' => $color];

            // Straight back with a slight outward splay, then curving down behind the ear.
            $at = fn (float $d) => [$side * ($hinge + $d * 0.045), $hingePoint[1] - max(0, $d - 100) * 0.9, $hingePoint[2] - $d];
            $end = [$side * ($hinge + 5.6), $hingePoint[1] - 22, $hingePoint[2] - 130];
            $templeWidth = $temple['metal'] ? 2.4 : ($bold ? 4.4 : 3.6);
            if ($temple['tips']) {
                $lines[] = ['part' => 'temple', 'points' => [$hingePoint, $at(22), $at(52), $at(82), $at(92)], 'width' => $templeWidth, 'closed' => false, 'color' => $temple['color']];
                $lines[] = ['part' => 'tip', 'points' => [$at(86), $at(104), $at(116), $end], 'width' => 4.2, 'closed' => false, 'color' => $temple['tips']['color']];
            } else {
                $lines[] = ['part' => 'temple', 'points' => [$hingePoint, $at(22), $at(52), $at(82), $at(105), $at(118), $end], 'width' => $templeWidth, 'closed' => false, 'color' => $temple['color']];
            }
        }

        $tinted = $product->category === 'sunglasses';

        return [
            'color' => $color,
            'tinted' => $tinted,
            'lines' => self::round($lines),
            'style' => [
                'rim' => $rim,
                'finish' => $finish,
                // Gradient: what the colour fades to at the bottom; null = clear crystal.
                'fadeTo' => $finish === 'gradient' && ! Str::contains($look, ['crystal', 'clear', 'translucent']) ? self::mix($color, '#FFFFFF', 0.7) : null,
                'roughness' => $roughness,
                'thickness' => $rim === 'half' ? $brow : $thickness,
                'depth' => $bold ? 5.2 : 4.2,
                'wire' => $wire,
                'temple' => $temple,
                'lens' => [
                    'tint' => $tinted ? (self::named($lensText, self::LENS_TINTS) ?? '#2B2F33') : null,
                    'gradient' => $tinted && Str::contains($lensText, 'gradient'),
                    'mirror' => $tinted && Str::contains($lensText, 'mirror'),
                ],
            ],
        ];
    }

    /** Lens size and outline parameters for a shape name; unknown shapes are a soft rectangle. */
    private static function lensSpec(string $shape): array
    {
        $has = fn (string ...$words) => Str::contains($shape, $words);
        $base = ['w' => 52, 'h' => 38, 'top' => 4.2, 'bottom' => 3.2, 'taper' => 0.1, 'lift' => 0, 'droop' => 0, 'dy' => 0, 'bridge' => 18, 'sides' => 0, 'bar' => false];

        $spec = match (true) {
            $has('hex') => ['w' => 52, 'h' => 45, 'sides' => 6],
            $has('geometric', 'octagon') => ['w' => 52, 'h' => 45, 'sides' => 8],
            $has('navigator') => ['w' => 57, 'h' => 46, 'top' => 9, 'bottom' => 2.4, 'taper' => 0.26, 'droop' => 0.12, 'dy' => 1, 'bridge' => 16, 'bar' => true],
            $has('aviator', 'pilot') => ['w' => 58, 'h' => 50, 'top' => 2.6, 'bottom' => 2, 'taper' => 0.3, 'droop' => 0.22, 'dy' => 2, 'bridge' => 15, 'bar' => true],
            $has('shield', 'flat-top', 'flat top') => ['w' => 60, 'h' => 46, 'top' => 9, 'bottom' => 2.6, 'taper' => 0.18, 'bridge' => 16],
            $has('cat') => ['w' => 54, 'h' => 40, 'top' => 3, 'bottom' => 2.4, 'taper' => 0.12, 'lift' => 0.38, 'bridge' => 17],
            $has('browline', 'clubmaster') => ['w' => 50, 'h' => 41, 'top' => 4.6, 'bottom' => 2.5, 'bridge' => 20],
            $has('panto') => ['w' => 48, 'h' => 44, 'top' => 2.8, 'bottom' => 2, 'taper' => 0.06, 'bridge' => 20],
            $has('round') => ['w' => 46, 'h' => 46, 'top' => 2, 'bottom' => 2, 'taper' => 0.04, 'bridge' => 21],
            $has('oval') => ['w' => 52, 'h' => $has('slim') ? 34 : 38, 'top' => 2.3, 'bottom' => 2.1, 'taper' => 0.06],
            $has('square') => [
                'w' => $has('oversized') ? 57 : ($has('bold') ? 54 : 51),
                'h' => $has('oversized') ? 52 : ($has('bold') ? 48 : 45),
                'top' => $has('architectural') ? 7 : 5.2,
                'bottom' => $has('architectural') ? 6 : 4.2,
                'taper' => 0.08,
            ],
            $has('slim') && $has('rectangle') => ['w' => 54, 'h' => 32, 'top' => 5, 'bottom' => 3.6, 'taper' => 0.08],
            $has('rectangle') && ! $has('soft') => ['w' => 54, 'h' => 38, 'top' => 5.5, 'bottom' => 4.2, 'taper' => 0.08],
            default => [],
        };

        return $spec + $base;
    }

    /**
     * Lens outline around ($cx, dy), counter-clockwise from the outer (temple-side) point:
     * a superellipse with its own top and bottom roundness, the lower nasal corner
     * swung out for the nose pads, plus cat-eye lift or aviator droop.
     */
    private static function lens(float $cx, int $side, array $spec): array
    {
        $a = $spec['w'] / 2;
        $b = $spec['h'] / 2;
        if ($spec['sides']) {
            $c = $spec['sides'] === 8 ? 0.22 * min($spec['w'], $spec['h']) : 0;
            $corners = $spec['sides'] === 6
                ? [[$a, 0], [$a / 2, $b], [-$a / 2, $b], [-$a, 0], [-$a / 2, -$b], [$a / 2, -$b]]
                : [[$a, $b - $c], [$a - $c, $b], [-$a + $c, $b], [-$a, $b - $c], [-$a, -$b + $c], [-$a + $c, -$b], [$a - $c, -$b], [$a, -$b + $c]];

            return array_map(fn (array $p) => [$cx + $side * $p[0], $p[1] + $spec['dy'], 0], $side > 0 ? $corners : self::mirrorOrder($corners));
        }

        $points = [];
        for ($i = 0; $i < self::LENS_POINTS; $i++) {
            $t = 2 * M_PI * $i / self::LENS_POINTS;
            $n = sin($t) >= 0 ? $spec['top'] : $spec['bottom'];
            $x = $a * self::spow(cos($t), 2 / $n);
            $y = $b * self::spow(sin($t), 2 / $n);
            $outer = $x / $a; // +1 at the temple, -1 at the nose
            if ($y < 0) {
                $x += $spec['taper'] * $a * max(0, -$outer) * (-$y / $b) ** 1.5;
                $y -= $spec['droop'] * $b * max(0, $outer) ** 2 * (-$y / $b);
            } else {
                $y += $spec['lift'] * $b * max(0, $outer) ** 3;
            }
            $points[] = [$cx + $side * $x, $y + $spec['dy'], 0];
        }

        // The left lens is mirrored; reverse it so both outlines run the same way round.
        return $side > 0 ? $points : array_merge([$points[0]], array_reverse(array_slice($points, 1)));
    }

    /** The part of a closed outline at or above $y, in order along the outline. */
    private static function topRun(array $points, float $y): array
    {
        $n = count($points);
        $in = array_map(fn (array $p) => $p[1] >= $y, $points);
        $start = 0;
        while ($start < $n && ! ($in[$start] && ! $in[($start + $n - 1) % $n])) {
            $start++;
        }
        $run = [];
        for ($i = 0; $i < $n && $in[($start + $i) % $n]; $i++) {
            $run[] = $points[($start + $i) % $n];
        }

        return $run;
    }

    /** Mirrored polygon corners in counter-clockwise order. */
    private static function mirrorOrder(array $corners): array
    {
        return array_merge([$corners[0]], array_reverse(array_slice($corners, 1)));
    }

    /** Where a horizontal line at $y leaves the lens on the temple side. */
    private static function outerX(array $points, float $y, int $side): float
    {
        $best = 0.0;
        $count = count($points);
        for ($i = 0; $i < $count; $i++) {
            [$x1, $y1] = $points[$i];
            [$x2, $y2] = $points[($i + 1) % $count];
            if (($y1 - $y) * ($y2 - $y) > 0 || $y1 === $y2) {
                continue;
            }
            $best = max($best, $side * ($x1 + ($y - $y1) / ($y2 - $y1) * ($x2 - $x1)));
        }

        return $best;
    }

    private static function temple(string $text, string $rim, string $color, string $finish, array $finishes): array
    {
        $metalFront = in_array($rim, ['metal', 'rimless'], true);
        $metal = $text === '' ? $metalFront : self::isMetal($text) || Str::contains($text, 'metal');
        // Tips match the front on acetate frames; "…-tipped" temples use the second finish colour.
        $tipColor = Str::contains($text, 'tip') && isset($finishes[1]) ? $finishes[1] : ($metalFront ? '#1F1F1F' : $color);
        $tipFinish = $metalFront || $finish === 'tortoise' ? 'solid' : ($finish === 'solid' ? 'solid' : 'crystal');

        if ($metal) {
            return [
                'metal' => true,
                'color' => self::named($text, self::METAL_COLORS) ?? ($metalFront ? $color : self::SILVER),
                'finish' => 'solid',
                'tips' => $metalFront && ! Str::contains($text, 'tip') ? null : ['color' => $tipColor, 'finish' => $tipFinish],
            ];
        }

        $named = self::named($text, self::ACETATE_COLORS);
        $templeFinish = match (true) {
            Str::contains($text, ['tortoise', 'havana']) => 'tortoise',
            Str::contains($text, ['crystal', 'clear', 'smoke', 'horn', 'translucent']) => 'crystal',
            $named !== null => 'solid',
            default => $finish === 'gradient' ? 'crystal' : $finish,
        };
        if ($templeFinish === 'tortoise' && $finish === 'tortoise') {
            $named = $color;
        }

        return [
            'metal' => false,
            'color' => $named ?? $color,
            'finish' => $templeFinish,
            'tips' => Str::contains($text, 'tip') && isset($finishes[1]) ? ['color' => $finishes[1], 'finish' => 'solid'] : null,
        ];
    }

    private static function isMetal(string $text): bool
    {
        return Str::contains($text, ['titanium', 'steel', 'metal', 'alumin', 'alloy', 'monel'])
            && ! Str::contains($text, ['acetate', 'tr90', 'tr-90', 'plastic', 'nylon', 'propionate', 'grilamid', 'ultem']);
    }

    /** First colour whose word appears in the text. */
    private static function named(string $text, array $colors): ?string
    {
        foreach ($colors as $word => $hex) {
            if (Str::contains($text, $word)) {
                return $hex;
            }
        }

        return null;
    }

    private static function mix(string $from, string $to, float $t): string
    {
        $rgb = fn (string $hex) => array_map(fn (int $i) => hexdec(substr(ltrim($hex, '#'), $i, 2)), [0, 2, 4]);
        [$a, $b] = [$rgb($from), $rgb($to)];

        return sprintf('#%02X%02X%02X', ...array_map(fn (int $i) => (int) round($a[$i] + ($b[$i] - $a[$i]) * $t), [0, 1, 2]));
    }

    private static function spow(float $v, float $p): float
    {
        return ($v < 0 ? -1 : 1) * abs($v) ** $p;
    }

    /** Keep the JSON payload small. */
    private static function round(array $lines): array
    {
        foreach ($lines as &$line) {
            $line['points'] = array_map(fn (array $p) => array_map(fn ($v) => round((float) $v, 2), $p), $line['points']);
        }

        return $lines;
    }
}
