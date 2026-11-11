<?php

namespace Database\Seeders;

use App\Models\Coupon;
use App\Models\FrameSize;
use App\Models\LensOption;
use App\Models\LensSku;
use App\Models\PowerType;
use App\Models\ShippingZone;
use Illuminate\Database\Seeder;

/**
 * Store configuration: frame sizes, power types, lens packages, lens stock,
 * shipping zones and coupons. Frames themselves come from ProductSeeder.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'XS', 'width' => 124, 'lens_width' => 46, 'bridge' => 15, 'temple' => 130, 'face' => 'narrow'],
            ['code' => 'S', 'width' => 130, 'lens_width' => 48, 'bridge' => 16, 'temple' => 135, 'face' => 'narrow to medium'],
            ['code' => 'M', 'width' => 138, 'lens_width' => 52, 'bridge' => 18, 'temple' => 145, 'face' => 'medium'],
            ['code' => 'L', 'width' => 146, 'lens_width' => 56, 'bridge' => 19, 'temple' => 150, 'face' => 'medium to wide'],
        ] as $i => $size) {
            FrameSize::query()->updateOrCreate(['code' => $size['code']], $size + ['sort_order' => $i]);
        }

        foreach ([
            ['code' => 'single', 'label' => 'With Power', 'description' => 'Single vision for distance or near', 'price' => 0, 'needs_lens' => true, 'needs_rx' => true],
            ['code' => 'zero', 'label' => 'Zero Power BLU Screen', 'description' => 'Blue-light defense without correction', 'price' => 0, 'needs_lens' => true, 'needs_rx' => false],
            ['code' => 'progressive', 'label' => 'Progressive / Bifocals', 'description' => 'Seamless multifocal surfacing', 'price' => 120, 'needs_lens' => true, 'needs_rx' => true],
            ['code' => 'frame', 'label' => 'Frame Only', 'description' => 'Keep the frame, fit lenses later', 'price' => 0, 'needs_lens' => false, 'needs_rx' => false],
        ] as $i => $type) {
            PowerType::query()->updateOrCreate(['code' => $type['code']], $type + ['sort_order' => $i]);
        }

        foreach ([
            ['code' => 'antiglare', 'label' => 'Anti-Glare Premium', 'description' => 'Diamond-turned AR, zero distortion', 'price' => 0, 'badge' => 'Included',
                'features' => ['Anti-reflective coating', '1.56 index lens', 'Scratch resistant', '6-month lens warranty']],
            ['code' => 'highindex', 'label' => 'High-Index 1.74', 'description' => '35% thinner for strong prescriptions', 'price' => 150, 'badge' => 'Thinnest',
                'features' => ['Up to 35% thinner & lighter', 'Recommended for power above ±4.00', 'Blue-light filter', 'Water & dust repellent', '1-year lens warranty']],
            ['code' => 'photochromic', 'label' => 'Photochromic Transition', 'description' => 'Clear indoors, tinted in sun', 'price' => 180, 'badge' => 'Most popular',
                'features' => ['Darkens outdoors in under 30 seconds', '100% UV400 protection', 'Blue-light filter', 'Anti-reflective coating', '1-year lens warranty']],
        ] as $i => $option) {
            LensOption::query()->updateOrCreate(['code' => $option['code']], $option + ['sort_order' => $i]);
        }

        foreach ([
            ['sku' => 'LN-CR39-150', 'name' => 'CR-39 Standard', 'substrate' => 'CR-39', 'refractive_index' => '1.50', 'abbe' => 58, 'geometry' => 'Spherical', 'coatings' => ['Hard coat', 'Anti-reflective'], 'labs' => ['Zürich', 'Paris'], 'stock' => 820],
            ['sku' => 'LN-MR8-160', 'name' => 'MR-8 Thin', 'substrate' => 'MR-8', 'refractive_index' => '1.60', 'abbe' => 41, 'geometry' => 'Aspheric', 'coatings' => ['Anti-reflective', 'Blue 420nm'], 'labs' => ['Zürich'], 'stock' => 410],
            ['sku' => 'LN-UD-174', 'name' => '1.74 Ultra-Dense', 'substrate' => 'MR-174', 'refractive_index' => '1.74', 'abbe' => 33, 'geometry' => 'Double Aspheric', 'coatings' => ['Hard coat', 'Anti-reflective', 'Blue 420nm', 'Hydrophobic', 'Oleophobic'], 'labs' => ['Zürich', 'Tokyo'], 'stock' => 38],
            ['sku' => 'LN-CG-152', 'name' => 'Crown Glass Mineral', 'substrate' => 'Crown Glass', 'refractive_index' => '1.52', 'abbe' => 59, 'geometry' => 'Toric Base-6', 'coatings' => ['Anti-reflective', 'Oleophobic'], 'labs' => ['Milan'], 'stock' => 126],
            ['sku' => 'LN-PH-167', 'name' => 'Photochromic 1.67', 'substrate' => 'MR-8', 'refractive_index' => '1.67', 'abbe' => 32, 'geometry' => 'Freeform', 'coatings' => ['Hard coat', 'Anti-reflective'], 'labs' => ['Paris'], 'stock' => 9],
        ] as $sku) {
            LensSku::query()->updateOrCreate(['sku' => $sku['sku']], $sku);
        }

        foreach ([
            ['name' => 'Switzerland', 'courier' => 'Swiss Post Express', 'countries' => ['CH'], 'transit_min' => 1, 'transit_max' => 2, 'rate_label' => 'CHF 0'],
            ['name' => 'EU / UK', 'courier' => 'DHL Express Carbon Neutral', 'countries' => ['DE', 'FR', 'GB'], 'transit_min' => 2, 'transit_max' => 4, 'rate_label' => '$18'],
            ['name' => 'International', 'courier' => 'FedEx Priority International', 'countries' => ['IN', 'US', 'BD'], 'transit_min' => 3, 'transit_max' => 5, 'rate_label' => '$32'],
        ] as $i => $zone) {
            ShippingZone::query()->updateOrCreate(['name' => $zone['name']], $zone + ['sort_order' => $i, 'holidays' => [], 'enabled' => true]);
        }

        Coupon::query()->updateOrCreate(['code' => 'ATELIER10'], ['type' => 'percent', 'value' => 10, 'is_active' => true]);
    }
}
