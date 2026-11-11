<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

/**
 * The live REFRA frame catalogue. Photos live in public/images/products.
 * Re-running updates frames in place by slug and never duplicates them.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $products = [
            [
                'slug' => 'rose-gradient', 'name' => 'Rosé Gradient', 'series' => 'Crystal Fade Collection', 'badge' => 'New Arrival',
                'category' => 'eyeglasses', 'price' => 189, 'compare_at_price' => 249, 'cost' => 46, 'sku' => 'RF-AC-ROSE', 'stock' => 32,
                'shape' => 'Soft Rectangle', 'color' => 'Rosé to Crystal Fade', 'color_name' => 'Rosé', 'material' => 'Crystal Gradient Acetate',
                'temple_material' => 'Stainless Steel', 'lens_tag' => 'Lightweight', 'weight' => '15 g', 'finishes' => ['#C98A84'],
                'brand' => 'REFRA Studio', 'model_no' => 'RS-RG1', 'gender' => 'women', 'frame_type' => 'full_rim',
                'offer_text' => 'with Free Anti-Glare lenses',
                'description' => 'A soft rectangle in hand-polished acetate that fades from rosé at the brow to clear crystal below, on slim stainless steel temples.',
            ],
            [
                'slug' => 'heritage-gold', 'name' => 'Heritage Square Gold', 'series' => 'Heritage Metal Series', 'badge' => 'Atelier Pick',
                'category' => 'eyeglasses', 'price' => 329, 'compare_at_price' => null, 'cost' => 84, 'sku' => 'RF-TI-HER', 'stock' => 20,
                'shape' => 'Classic Square', 'color' => 'Brushed Champagne Gold & Burgundy', 'color_name' => 'Champagne Gold', 'material' => 'Brushed Gold Titanium',
                'temple_material' => 'Burgundy Acetate', 'lens_tag' => 'Titanium Frame', 'weight' => '13 g', 'finishes' => ['#C2A47A'],
                'brand' => 'REFRA Atelier', 'model_no' => 'RA-HS2', 'gender' => 'unisex', 'frame_type' => 'full_rim',
                'offer_text' => 'with Free Anti-Glare lenses',
                'description' => 'A softened square in brushed champagne titanium with adjustable nose pads, engraved medallion end pieces and burgundy acetate temple tips.',
            ],
            [
                'slug' => 'havana-keyhole', 'name' => 'Havana Keyhole', 'series' => 'Classic Acetate Series', 'badge' => 'Bestseller',
                'category' => 'eyeglasses', 'price' => 249, 'compare_at_price' => 299, 'cost' => 62, 'sku' => 'RF-AC-HAV', 'stock' => 26,
                'shape' => 'Keyhole Panto', 'color' => 'Havana Tortoise', 'color_name' => 'Havana', 'material' => 'Havana Tortoise Acetate',
                'temple_material' => 'Acetate', 'lens_tag' => 'Hand Polished', 'weight' => '22 g', 'finishes' => ['#8A4A24'],
                'brand' => 'REFRA Atelier', 'model_no' => 'RA-HK3', 'gender' => 'unisex', 'frame_type' => 'full_rim',
                'offer_text' => 'Blue-light lenses at no extra cost',
                'description' => 'A rounded panto in warm havana tortoise with a sculpted keyhole bridge and twin-pin rivets — an everyday classic that suits most faces.',
            ],
            [
                'slug' => 'noir-sovereign', 'name' => 'Noir Sovereign', 'series' => 'Sovereign Acetate Edition', 'badge' => 'Limited Edition',
                'category' => 'eyeglasses', 'price' => 389, 'compare_at_price' => null, 'cost' => 101, 'sku' => 'RF-AC-NOIR', 'stock' => 12,
                'shape' => 'Bold Square', 'color' => 'Polished Black & Smoke Horn', 'color_name' => 'Noir', 'material' => 'Polished Black Acetate',
                'temple_material' => 'Smoke Horn Acetate', 'lens_tag' => 'Statement Frame', 'weight' => '26 g', 'finishes' => ['#111214'],
                'brand' => 'REFRA Atelier', 'model_no' => 'RA-NS4', 'gender' => 'men', 'frame_type' => 'full_rim',
                'offer_text' => 'with Free Anti-Glare lenses',
                'description' => 'A thick, sculpted square front in deep polished black, finished with gold-tone rivets and smoke-horn temples for real presence.',
            ],
            [
                'slug' => 'glacier-slim', 'name' => 'Glacier Slim', 'series' => 'Featherlight TR Series', 'badge' => 'Featherlight',
                'category' => 'eyeglasses', 'price' => 169, 'compare_at_price' => 199, 'cost' => 38, 'sku' => 'RF-TR-GLA', 'stock' => 40,
                'shape' => 'Slim Rectangle', 'color' => 'Translucent Glacier Blue', 'color_name' => 'Glacier Blue', 'material' => 'Translucent TR90',
                'temple_material' => 'Stainless Steel', 'lens_tag' => 'Flexible TR90', 'weight' => '11 g', 'finishes' => ['#7F9BB5'],
                'brand' => 'REFRA Studio', 'model_no' => 'RS-GS5', 'gender' => 'unisex', 'frame_type' => 'full_rim',
                'offer_text' => 'Blue-light lenses at no extra cost',
                'description' => 'A clean slim rectangle in flexible translucent blue TR90 with steel temples — almost weightless for long days at the screen.',
            ],
            [
                'slug' => 'vector-navigator', 'name' => 'Vector Navigator', 'series' => 'Double Bridge Metal Series', 'badge' => 'Double Bridge',
                'category' => 'eyeglasses', 'price' => 229, 'compare_at_price' => null, 'cost' => 57, 'sku' => 'RF-MT-VEC', 'stock' => 18,
                'shape' => 'Navigator Aviator', 'color' => 'Matte Black', 'color_name' => 'Matte Black', 'material' => 'Matte Black Stainless Steel',
                'temple_material' => 'Stainless Steel', 'lens_tag' => 'Metal Frame', 'weight' => '16 g', 'finishes' => ['#1C1C1E'],
                'brand' => 'REFRA Studio', 'model_no' => 'RS-VN6', 'gender' => 'men', 'frame_type' => 'full_rim',
                'offer_text' => 'with Free Anti-Glare lenses',
                'description' => 'A squared navigator with a straight double bridge in matte black steel, fitted with soft adjustable nose pads.',
            ],
            [
                'slug' => 'milano-tortoise', 'name' => 'Milano Tortoise', 'series' => 'Designed in Italy', 'badge' => 'Designed in Italy',
                'category' => 'eyeglasses', 'price' => 349, 'compare_at_price' => 420, 'cost' => 88, 'sku' => 'RF-AC-MIL', 'stock' => 14,
                'shape' => 'Oversized Bold Square', 'color' => 'Amber Tortoise', 'color_name' => 'Amber Tortoise', 'material' => 'Amber Tortoise Acetate',
                'temple_material' => 'Acetate', 'lens_tag' => 'Italian Acetate', 'weight' => '27 g', 'finishes' => ['#5A3A1E'],
                'brand' => 'REFRA Atelier', 'model_no' => 'RA-MT7', 'gender' => 'women', 'frame_type' => 'full_rim',
                'offer_text' => 'with Free Anti-Glare lenses',
                'description' => 'An oversized square in richly mottled amber tortoise with medallion hinges — confident, warm and unmistakably Italian.',
            ],
            [
                'slug' => 'prism-hexa', 'name' => 'Prism Hexa', 'series' => 'Geometric Crystal Series', 'badge' => 'Geometric',
                'category' => 'eyeglasses', 'price' => 299, 'compare_at_price' => null, 'cost' => 74, 'sku' => 'RF-AC-HEX', 'stock' => 22,
                'shape' => 'Geometric Hexagon', 'color' => 'Smoke Crystal & Rose Gold', 'color_name' => 'Smoke Crystal', 'material' => 'Smoke Crystal Acetate',
                'temple_material' => 'Rose Gold Metal & Acetate', 'lens_tag' => 'Mixed Material', 'weight' => '18 g', 'finishes' => ['#9A8F88'],
                'brand' => 'REFRA Atelier', 'model_no' => 'RA-PH8', 'gender' => 'women', 'frame_type' => 'full_rim',
                'offer_text' => 'Blue-light lenses at no extra cost',
                'description' => 'A soft hexagon in smoke crystal acetate wrapped around a rose-gold metal inner rim, with a sculpted bridge and medallion end pieces.',
            ],

            // Sunglasses
            [
                'slug' => 'ember-oval', 'name' => 'Ember Oval', 'series' => 'Retro Sun Edit', 'badge' => 'New Arrival',
                'category' => 'sunglasses', 'price' => 159, 'compare_at_price' => 189, 'cost' => 40, 'sku' => 'RF-SN-EMB', 'stock' => 28,
                'shape' => 'Slim Oval', 'color' => 'Ember Tortoise with Smoke Gradient Lenses', 'color_name' => 'Ember Tortoise', 'material' => 'Tortoise Acetate',
                'temple_material' => 'Acetate', 'lens_tag' => 'UV400', 'weight' => '21 g', 'finishes' => ['#5B2A14'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-EO1', 'gender' => 'unisex', 'frame_type' => 'full_rim',
                'offer_text' => 'Free hard case included',
                'description' => 'A slim, faceted oval in fiery ember tortoise with smoke gradient lenses, a nod to late-90s minimalism.',
            ],
            [
                'slug' => 'mahogany-square', 'name' => 'Mahogany Square', 'series' => 'Urban Sun Series', 'badge' => 'Polarized',
                'category' => 'sunglasses', 'price' => 179, 'compare_at_price' => null, 'cost' => 45, 'sku' => 'RF-SN-MAH', 'stock' => 30,
                'shape' => 'Bold Square', 'color' => 'Mahogany Tortoise with Grey Lenses', 'color_name' => 'Mahogany', 'material' => 'Mahogany Tortoise Acetate',
                'temple_material' => 'Acetate', 'lens_tag' => 'Polarized', 'weight' => '27 g', 'finishes' => ['#3B1A1A'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-MS2', 'gender' => 'men', 'frame_type' => 'full_rim',
                'offer_text' => 'Polarized lenses at no extra cost',
                'description' => 'A strong square in deep mahogany tortoise with polarized grey lenses that cut glare on the road and by the water.',
            ],
            [
                'slug' => 'onyx-shield', 'name' => 'Onyx Shield', 'series' => 'Rebel Sun Series', 'badge' => 'Statement',
                'category' => 'sunglasses', 'price' => 229, 'compare_at_price' => 269, 'cost' => 58, 'sku' => 'RF-SN-ONX', 'stock' => 16,
                'shape' => 'Flat-Top Shield', 'color' => 'Gloss Black & Gunmetal with Green Gradient Lenses', 'color_name' => 'Onyx', 'material' => 'Gloss Black Acetate',
                'temple_material' => 'Gunmetal & Acetate', 'lens_tag' => 'Polarized', 'weight' => '30 g', 'finishes' => ['#101010'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-OS3', 'gender' => 'men', 'frame_type' => 'full_rim',
                'offer_text' => 'Polarized lenses at no extra cost',
                'description' => 'An angular flat-top shield in gloss black with faceted gunmetal hinges and polarized green gradient lenses.',
            ],
            [
                'slug' => 'tortoise-browline', 'name' => 'Tortoise Browline', 'series' => 'Urban Sun Series', 'badge' => 'Bestseller',
                'category' => 'sunglasses', 'price' => 199, 'compare_at_price' => null, 'cost' => 50, 'sku' => 'RF-SN-BRW', 'stock' => 24,
                'shape' => 'Browline', 'color' => 'Tortoise & Rose Gold with Green Lenses', 'color_name' => 'Tortoise Rose Gold', 'material' => 'Acetate & Metal',
                'temple_material' => 'Tortoise Acetate', 'lens_tag' => 'Polarized', 'weight' => '24 g', 'finishes' => ['#6B3F1F'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-TB4', 'gender' => 'unisex', 'frame_type' => 'half_rim',
                'offer_text' => 'Polarized lenses at no extra cost',
                'description' => 'A timeless browline in mottled tortoise over a rose-gold lower rim, with adjustable nose pads and polarized green lenses.',
            ],
            [
                'slug' => 'bronze-navigator', 'name' => 'Bronze Navigator', 'series' => 'Double Bridge Sun Series', 'badge' => 'Double Bridge',
                'category' => 'sunglasses', 'price' => 219, 'compare_at_price' => 259, 'cost' => 55, 'sku' => 'RF-SN-NAV', 'stock' => 20,
                'shape' => 'Rectangle Navigator', 'color' => 'Matte Black with Brown Gradient Lenses', 'color_name' => 'Black Bronze', 'material' => 'Matte Black Stainless Steel',
                'temple_material' => 'Stainless Steel', 'lens_tag' => 'Gradient', 'weight' => '19 g', 'finishes' => ['#1E1A18'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-BN5', 'gender' => 'men', 'frame_type' => 'full_rim',
                'offer_text' => 'Free hard case included',
                'description' => 'A squared double-bridge navigator in matte black steel with warm brown gradient lenses and soft nose pads.',
            ],
            [
                'slug' => 'petal-kids-round', 'name' => 'Petal Kids Round', 'series' => 'Little Sun Collection', 'badge' => 'Kids',
                'category' => 'sunglasses', 'price' => 89, 'compare_at_price' => 109, 'cost' => 22, 'sku' => 'RF-SN-KID', 'stock' => 35,
                'shape' => 'Round', 'color' => 'Matte Black & Petal Pink with Rose Gradient Lenses', 'color_name' => 'Black Petal', 'material' => 'Flexible TR90',
                'temple_material' => 'Silicone-Tipped TR90', 'lens_tag' => 'Flexible & Unbreakable', 'weight' => '14 g', 'finishes' => ['#2A2A2A', '#F2C4D6'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-PK6', 'gender' => 'kids', 'frame_type' => 'full_rim',
                'offer_text' => 'Polarized UV400 lenses included',
                'description' => 'A bendy, near-unbreakable round frame for kids with soft pink temple tips, a strap loop and rose gradient UV400 lenses.',
            ],
            [
                'slug' => 'peach-crystal-cat-eye', 'name' => 'Peach Crystal Cat-Eye', 'series' => 'Crystal Sun Edit', 'badge' => 'Polarized',
                'category' => 'sunglasses', 'price' => 169, 'compare_at_price' => null, 'cost' => 42, 'sku' => 'RF-SN-PCH', 'stock' => 22,
                'shape' => 'Soft Cat-Eye', 'color' => 'Peach Crystal with Brown Lenses', 'color_name' => 'Peach Crystal', 'material' => 'Crystal Acetate',
                'temple_material' => 'Crystal Acetate', 'lens_tag' => 'Polarized', 'weight' => '20 g', 'finishes' => ['#E9B99A'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-PC7', 'gender' => 'women', 'frame_type' => 'full_rim',
                'offer_text' => 'Polarized lenses at no extra cost',
                'description' => 'A softly lifted cat-eye in translucent peach crystal with polarized brown lenses, made for golden-hour light.',
            ],
            [
                'slug' => 'graphite-geo', 'name' => 'Graphite Geo', 'series' => 'Urban Sun Series', 'badge' => 'Lightweight',
                'category' => 'sunglasses', 'price' => 209, 'compare_at_price' => 239, 'cost' => 53, 'sku' => 'RF-SN-GEO', 'stock' => 18,
                'shape' => 'Geometric Square', 'color' => 'Matte Black with Grey Lenses', 'color_name' => 'Graphite', 'material' => 'Matte TR90 & Metal',
                'temple_material' => 'Aluminium', 'lens_tag' => 'Polarized', 'weight' => '18 g', 'finishes' => ['#1A1A1A'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-GG8', 'gender' => 'unisex', 'frame_type' => 'full_rim',
                'offer_text' => 'Polarized lenses at no extra cost',
                'description' => 'A faceted geometric square in matte graphite with light aluminium temples, nose pads and polarized grey lenses.',
            ],
            [
                'slug' => 'azure-aviator', 'name' => 'Azure Mirror Aviator', 'series' => 'Classic Aviator Series', 'badge' => 'Mirror Lens',
                'category' => 'sunglasses', 'price' => 149, 'compare_at_price' => 179, 'cost' => 36, 'sku' => 'RF-SN-AVI', 'stock' => 40,
                'shape' => 'Aviator', 'color' => 'Silver with Blue Mirror Lenses', 'color_name' => 'Silver Azure', 'material' => 'Polished Silver Metal',
                'temple_material' => 'Metal with Acetate Tips', 'lens_tag' => 'Mirrored UV400', 'weight' => '17 g', 'finishes' => ['#C0C4C8', '#5AA9E6'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-AA9', 'gender' => 'unisex', 'frame_type' => 'full_rim',
                'offer_text' => 'Free hard case included',
                'description' => 'The classic double-bridge aviator in polished silver, fitted with ice-blue mirrored UV400 lenses.',
            ],
            [
                'slug' => 'amber-square', 'name' => 'Amber Square', 'series' => 'Designed in Italy', 'badge' => 'Gradient',
                'category' => 'sunglasses', 'price' => 189, 'compare_at_price' => null, 'cost' => 47, 'sku' => 'RF-SN-AMB', 'stock' => 26,
                'shape' => 'Square', 'color' => 'Amber Tortoise with Brown Gradient Lenses', 'color_name' => 'Amber Tortoise', 'material' => 'Amber Tortoise Acetate',
                'temple_material' => 'Acetate with Gold Accent', 'lens_tag' => 'Gradient', 'weight' => '25 g', 'finishes' => ['#7A3E16'],
                'brand' => 'REFRA Sun', 'model_no' => 'RSN-AS10', 'gender' => 'unisex', 'frame_type' => 'full_rim',
                'offer_text' => 'Free hard case included',
                'description' => 'A clean square in warm amber tortoise with gold temple accents and brown gradient lenses for everyday sun.',
            ],
        ];

        foreach ($products as $i => $product) {
            $model = Product::query()->updateOrCreate(
                ['slug' => $product['slug']],
                $product + ['image' => "images/products/{$product['slug']}.jpg", 'status' => 'active', 'variant_group' => null, 'sort_order' => $i],
            );
            if ($model->wasRecentlyCreated) {
                $model->forceFill(['views' => rand(1200, 9000)])->save();
            }

            if ($model->images()->doesntExist()) {
                // Slide 1 on the product page is the live 360° view, so the photo leads the gallery.
                $model->images()->create(['path' => $model->image, 'alt' => "{$model->name} — {$model->color}", 'sort_order' => 0]);
                foreach (['images/face.jpg', 'images/runway.jpg', 'images/gallery.jpg'] as $n => $path) {
                    $model->images()->create(['path' => $path, 'alt' => "Styling {$model->name}", 'is_lifestyle' => true, 'sort_order' => 10 + $n]);
                }
            }
        }
    }
}
