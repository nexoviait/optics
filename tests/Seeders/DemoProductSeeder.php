<?php

namespace Tests\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * Fixed demo frames the feature tests are written against: a rimless frame,
 * sunglasses, a colour variant pair and known prices. Kept separate from the
 * live catalogue (Database\Seeders\ProductSeeder) so the shop's products can
 * change without rewriting the test suite.
 */
class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        $bronze = '#C9A887';
        $obsidian = '#0F1113';
        $sage = '#8A9A8A';
        $titanium = '#6B7078';

        $products = [
            ['slug' => 'apex-01', 'name' => 'Apex 01 Titanium', 'series' => 'Prism Precision Series', 'badge' => 'Titanium Flagship', 'category' => 'eyeglasses', 'price' => 540, 'cost' => 142, 'sku' => 'RF-TI-APEX01', 'stock' => 24, 'image' => 'images/frame-apex.jpg', 'shape' => 'Architectural Square', 'color' => 'Champagne Bronze & Obsidian', 'material' => 'Japanese β-Titanium', 'lens_tag' => 'Titanium Frame', 'weight' => '12 g', 'finishes' => [$bronze, $obsidian, $sage], 'description' => 'Our signature square monolith machined from a single billet of Japanese β-titanium, calibrated to 0.02 mm fitting tolerance.'],
            ['slug' => 'aura-prism', 'name' => 'Aura Prism Sun', 'series' => '100% UV400 Polarized', 'badge' => 'Prism Polarized', 'category' => 'sunglasses', 'price' => 620, 'cost' => 158, 'sku' => 'RF-AC-AURA', 'stock' => 38, 'image' => 'images/frame-aura.jpg', 'shape' => 'Classic Rounded Panto', 'color' => 'Sage Clear & Polarized Green', 'material' => 'Mazzucchelli Bio-Acetate', 'lens_tag' => 'UV400 Prism', 'weight' => '24 g', 'finishes' => [$obsidian, $sage, $bronze], 'description' => 'Hand-polished Italian bio-acetate with mineral polarized prism lenses that dissolve glare while preserving true color.'],
            ['slug' => 'kinetics', 'name' => 'Kinetics Rimless', 'series' => 'Pure Beta Titanium', 'badge' => 'Feather Ultralight 12.4g', 'category' => 'eyeglasses', 'price' => 680, 'cost' => 171, 'sku' => 'RF-TI-KIN', 'stock' => 6, 'image' => 'images/frame-kinetics.jpg', 'shape' => 'Beveled Rimless Contour', 'color' => 'Polished Titanium Raw', 'material' => 'Surgical Grade β-Titanium', 'lens_tag' => 'Surgical Grade', 'weight' => '12.4 g', 'finishes' => [$titanium, $bronze], 'description' => 'A floating rimless architecture with diamond-beveled lens edges, nearly weightless on the face.'],
            ['slug' => 'solstice', 'name' => 'Solstice Geometric', 'series' => 'Anti-Glare Spectrum Filter', 'badge' => 'Blue-Light Lab', 'category' => 'eyeglasses', 'price' => 590, 'cost' => 139, 'sku' => 'RF-MT-SOL', 'stock' => 22, 'image' => 'images/frame-solstice.jpg', 'shape' => 'Geometric Hexagon', 'color' => 'Champagne Refract & Amber', 'material' => 'Organic Bio-Acetate & Ti', 'lens_tag' => 'Spectrum Filter', 'weight' => '14 g', 'finishes' => [$bronze, $sage, $obsidian], 'description' => 'Hexagonal gold-tone geometry fitted with 420nm spectral defense lenses for long screen sessions.'],
            ['slug' => 'helios-02', 'name' => 'Helios Aviator 02', 'series' => 'Mineral Gradient Prism', 'badge' => 'Aviator Edition', 'category' => 'sunglasses', 'price' => 540, 'cost' => 131, 'sku' => 'RF-TI-HEL02', 'stock' => 15, 'image' => 'images/frame-solstice.jpg', 'shape' => 'Aviator Prism', 'color' => 'Gold & Bronze Gradient', 'material' => 'Japanese β-Titanium', 'lens_tag' => 'Cat 3 Gradient', 'weight' => '18 g', 'finishes' => [$bronze, $obsidian], 'description' => 'Double-bridge aviator in titanium with mineral gradient lenses tuned for high-altitude light.'],
            ['slug' => 'eclipse', 'name' => 'Eclipse Bold Acetate', 'series' => 'Polarized Obsidian', 'badge' => 'Runway Edit', 'category' => 'sunglasses', 'price' => 480, 'cost' => 118, 'sku' => 'RF-AC-ECL', 'stock' => 9, 'image' => 'images/frame-aura.jpg', 'shape' => 'Bold Square', 'color' => 'Obsidian Black', 'material' => 'Mazzucchelli Bio-Acetate', 'lens_tag' => 'Polarized', 'weight' => '28 g', 'finishes' => [$obsidian, $sage], 'description' => 'A thick sculptural acetate front straight from the Milan runway, fitted with polarized obsidian lenses.'],
        ];
        // Brand, specification and merchandising attributes per frame.
        $details = [
            'apex-01' => ['brand' => 'REFRA Atelier', 'model_no' => 'RF-A01', 'gender' => 'men', 'frame_type' => 'full_rim', 'color_name' => 'Champagne Bronze', 'temple_material' => 'β-Titanium', 'compare_at_price' => 720, 'offer_text' => 'with Free Anti-Glare lenses', 'variant_group' => 'apex'],
            'aura-prism' => ['brand' => 'REFRA Sun', 'model_no' => 'RS-AP2', 'gender' => 'women', 'frame_type' => 'full_rim', 'color_name' => 'Sage', 'temple_material' => 'Bio-Acetate', 'compare_at_price' => 780, 'offer_text' => 'Polarized lenses included', 'variant_group' => 'aura'],
            'kinetics' => ['brand' => 'REFRA Atelier', 'model_no' => 'RF-K12', 'gender' => 'unisex', 'frame_type' => 'rimless', 'color_name' => 'Raw Titanium', 'temple_material' => 'β-Titanium', 'compare_at_price' => null, 'offer_text' => 'with Free Anti-Glare lenses', 'variant_group' => null],
            'solstice' => ['brand' => 'REFRA Lab', 'model_no' => 'RL-S06', 'gender' => 'unisex', 'frame_type' => 'full_rim', 'color_name' => 'Champagne', 'temple_material' => 'Titanium', 'compare_at_price' => 690, 'offer_text' => 'Blue-light lenses at no extra cost', 'variant_group' => null],
            'helios-02' => ['brand' => 'REFRA Sun', 'model_no' => 'RS-H02', 'gender' => 'men', 'frame_type' => 'full_rim', 'color_name' => 'Gold', 'temple_material' => 'β-Titanium', 'compare_at_price' => null, 'offer_text' => null, 'variant_group' => null],
            'eclipse' => ['brand' => 'REFRA Sun', 'model_no' => 'RS-E01', 'gender' => 'women', 'frame_type' => 'full_rim', 'color_name' => 'Obsidian', 'temple_material' => 'Bio-Acetate', 'compare_at_price' => 560, 'offer_text' => 'Polarized lenses included', 'variant_group' => 'aura'],
        ];

        // A second colourway of the Apex frame demonstrates linked colour variants.
        $products[] = ['slug' => 'apex-01-obsidian', 'name' => 'Apex 01 Titanium', 'series' => 'Prism Precision Series', 'badge' => 'New Colourway', 'category' => 'eyeglasses', 'price' => 540, 'cost' => 142, 'sku' => 'RF-TI-APEX01-OB', 'stock' => 18, 'image' => 'images/frame-apex.jpg', 'shape' => 'Architectural Square', 'color' => 'Obsidian Matte', 'material' => 'Japanese β-Titanium', 'lens_tag' => 'Titanium Frame', 'weight' => '12 g', 'finishes' => [$obsidian, $bronze], 'description' => 'The signature square monolith in a matte obsidian finish, machined from a single billet of Japanese β-titanium.'];
        $details['apex-01-obsidian'] = ['brand' => 'REFRA Atelier', 'model_no' => 'RF-A01-OB', 'gender' => 'men', 'frame_type' => 'full_rim', 'color_name' => 'Obsidian', 'temple_material' => 'β-Titanium', 'compare_at_price' => 720, 'offer_text' => 'with Free Anti-Glare lenses', 'variant_group' => 'apex'];

        foreach ($products as $i => $product) {
            $model = Product::query()->updateOrCreate(
                ['slug' => $product['slug']],
                $product + ($details[$product['slug']] ?? []) + ['sort_order' => $i, 'views' => rand(4000, 18000)],
            );

            if ($model->images()->doesntExist()) {
                $gallery = array_values(array_diff(['images/frame-apex.jpg', 'images/frame-aura.jpg', 'images/frame-kinetics.jpg', 'images/frame-solstice.jpg'], [$model->image]));
                foreach (array_slice($gallery, 0, 2) as $n => $path) {
                    $model->images()->create(['path' => $path, 'alt' => "{$model->name} — alternate view", 'sort_order' => $n]);
                }
                foreach (['images/face.jpg', 'images/runway.jpg', 'images/sun-portrait.jpg'] as $n => $path) {
                    $model->images()->create(['path' => $path, 'alt' => "Styling {$model->name}", 'is_lifestyle' => true, 'sort_order' => 10 + $n]);
                }
            }
        }
    }
}
