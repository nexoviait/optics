<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Support\FrameModel;
use Tests\TestCase;

/**
 * The frame model reads the catalogue text (shape, material, temple material,
 * colour) so the 3D view looks like the product.
 */
class FrameModelTest extends TestCase
{
    private function model(array $attributes): array
    {
        return FrameModel::for(new Product($attributes + ['category' => 'eyeglasses', 'frame_type' => 'full_rim']));
    }

    public function test_crystal_gradient_acetate_with_steel_temples(): void
    {
        $m = $this->model([
            'shape' => 'Soft Rectangle', 'material' => 'Crystal Gradient Acetate', 'temple_material' => 'Stainless Steel',
            'color' => 'Rosé to Crystal Fade', 'color_name' => 'Rosé', 'finishes' => ['#C98A84'],
        ]);

        $this->assertSame('acetate', $m['style']['rim']);
        $this->assertSame('gradient', $m['style']['finish']);
        $this->assertNull($m['style']['fadeTo'], 'A crystal gradient fades to clear.');
        $this->assertTrue($m['style']['temple']['metal']);
        $this->assertSame('#C3C7CC', $m['style']['temple']['color']);
        $this->assertSame(['color' => '#C98A84', 'finish' => 'crystal'], $m['style']['temple']['tips'], 'Steel temples on acetate get matching tips.');
        $this->assertNull($m['style']['lens']['tint']);
        $this->assertSame(['rim', 'rim', 'bridge', 'end', 'temple', 'tip', 'end', 'temple', 'tip'], array_column($m['lines'], 'part'));

        // 52 mm lenses either side of an 18 mm bridge.
        $right = array_column($m['lines'][1]['points'], 0);
        $this->assertEqualsWithDelta(9, min($right), 0.01);
        $this->assertEqualsWithDelta(61, max($right), 0.01);
    }

    public function test_metal_aviator_with_mirrored_lenses(): void
    {
        $m = $this->model([
            'category' => 'sunglasses', 'shape' => 'Aviator', 'material' => 'Polished Silver Metal',
            'temple_material' => 'Metal with Acetate Tips', 'color' => 'Silver with Blue Mirror Lenses', 'finishes' => ['#C0C4C8', '#5AA9E6'],
        ]);

        $this->assertSame('metal', $m['style']['rim']);
        $this->assertContains('bar', array_column($m['lines'], 'part'), 'Aviators have a double bridge.');
        $this->assertSame('#C0C4C8', $m['style']['temple']['color'], 'Metal temples match a metal front.');
        $this->assertSame('#5AA9E6', $m['style']['temple']['tips']['color']);
        $this->assertSame(['tint' => '#2C5C8F', 'gradient' => false, 'mirror' => true], $m['style']['lens']);
    }

    public function test_tortoise_browline_with_rose_gold_wire(): void
    {
        $m = $this->model([
            'category' => 'sunglasses', 'frame_type' => 'half_rim', 'shape' => 'Browline', 'material' => 'Acetate & Metal',
            'temple_material' => 'Tortoise Acetate', 'color' => 'Tortoise & Rose Gold with Green Lenses', 'color_name' => 'Tortoise Rose Gold',
            'finishes' => ['#6B3F1F'],
        ]);

        $this->assertSame('half', $m['style']['rim']);
        $this->assertSame('tortoise', $m['style']['finish']);
        $this->assertSame('#C9927E', $m['style']['wire']);
        $this->assertSame('tortoise', $m['style']['temple']['finish']);
        $this->assertSame('#3B5A3A', $m['style']['lens']['tint']);

        // Each brow runs along the top of its lens without cutting across it.
        $brows = array_values(array_filter($m['lines'], fn (array $l) => $l['part'] === 'brow'));
        $this->assertCount(2, $brows);
        foreach ($brows as $brow) {
            for ($i = 1; $i < count($brow['points']); $i++) {
                [$a, $b] = [$brow['points'][$i - 1], $brow['points'][$i]];
                $this->assertLessThan(15, hypot($b[0] - $a[0], $b[1] - $a[1]));
            }
        }
    }

    public function test_tortoise_and_named_temple_colours(): void
    {
        $havana = $this->model(['shape' => 'Keyhole Panto', 'material' => 'Havana Tortoise Acetate', 'temple_material' => 'Acetate', 'finishes' => ['#8A4A24']]);
        $this->assertSame('tortoise', $havana['style']['finish']);
        $this->assertSame(['metal' => false, 'color' => '#8A4A24', 'finish' => 'tortoise', 'tips' => null], $havana['style']['temple']);

        $heritage = $this->model(['shape' => 'Classic Square', 'material' => 'Brushed Gold Titanium', 'temple_material' => 'Burgundy Acetate', 'finishes' => ['#C2A47A']]);
        $this->assertSame('metal', $heritage['style']['rim']);
        $this->assertSame('#5E1A26', $heritage['style']['temple']['color']);
        $this->assertFalse($heritage['style']['temple']['metal']);
    }

    public function test_lens_outlines_follow_the_shape_name(): void
    {
        $lens = fn (string $shape) => $this->model(['shape' => $shape, 'material' => 'Acetate'])['lines'][1]['points'];
        $ratio = function (string $shape) use ($lens) {
            $points = $lens($shape);

            return (max(array_column($points, 0)) - min(array_column($points, 0))) / (max(array_column($points, 1)) - min(array_column($points, 1)));
        };

        $this->assertGreaterThan(1.5, $ratio('Slim Rectangle'));
        $this->assertEqualsWithDelta(1, $ratio('Round'), 0.05);

        // Cat-eye: the highest point is towards the temple, not over the middle of the lens.
        $points = $lens('Soft Cat-Eye');
        $centre = (max(array_column($points, 0)) + min(array_column($points, 0))) / 2;
        usort($points, fn (array $p, array $q) => $q[1] <=> $p[1]);
        $this->assertGreaterThan($centre + 10, $points[0][0]);

        // The lower nasal corner is cut away for the nose pads.
        $points = $lens('Soft Rectangle');
        $low = array_filter($points, fn (array $p) => $p[1] < -15);
        $high = array_filter($points, fn (array $p) => $p[1] > 15);
        $this->assertGreaterThan(min(array_column($high, 0)), min(array_column($low, 0)));
    }
}
