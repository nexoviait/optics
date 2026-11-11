<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Support\Settings;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $pages = [
            ['slug' => 'faq', 'title' => 'Frequently Asked Questions', 'footer_group' => 'help', 'body' => <<<'MD'
                ## Orders & delivery
                **How long does delivery take?**
                Frames are inspected and dispatched within 2 business days. Transit times per country are shown on every product page — enter your postal code under *Delivery & Services*.

                **Can I order without my prescription?**
                Yes. Choose *Submit power later* when selecting lenses and send it to us within 15 days from your account.

                ## Lenses
                **Which lens should I choose?**
                See our [Buying Guide](/pages/buying-guide). For power above ±4.00 we recommend high-index lenses.

                ## Returns
                **What is your exchange policy?**
                Unworn frames can be exchanged within 14 days. See [Shipping & Returns](/pages/shipping-returns).
                MD],
            ['slug' => 'buying-guide', 'title' => 'Buying Guide', 'footer_group' => 'help', 'body' => <<<'MD'
                ## 1. Pick your frame shape
                - **Round faces** suit rectangular and square frames.
                - **Square faces** suit round and panto frames.
                - **Oval faces** suit almost every shape.

                ## 2. Choose your size
                Use the [Frame Size Guide](/pages/frame-size-guide) or the size guide on each product page.

                ## 3. Select lenses
                | Lens | Best for |
                |---|---|
                | Anti-Glare Premium | Everyday wear, low power |
                | High-Index 1.74 | Strong prescriptions |
                | Photochromic | Indoor & outdoor use |
                MD],
            ['slug' => 'frame-size-guide', 'title' => 'Frame Size Guide', 'footer_group' => 'help', 'body' => <<<'MD'
                Every frame lists three numbers, for example **52-18-145**:

                1. **Lens width** (52 mm) — the horizontal width of one lens.
                2. **Bridge width** (18 mm) — the distance between the lenses.
                3. **Temple length** (145 mm) — the length of the arm.

                Measure a pair of glasses that fits you well and compare. Sizes XS to L are shown on each product page with a face-width matcher.
                MD],
            ['slug' => 'shipping-returns', 'title' => 'Shipping & Returns', 'footer_group' => 'help', 'body' => <<<'MD'
                - Complimentary insured express shipping on all orders.
                - 14-day exchange on unworn frames in original packaging.
                - Prescription lenses are made to order; we remake them free if your vision is not comfortable within 30 days.
                MD],
            ['slug' => 'warranty', 'title' => '365-Day Warranty', 'footer_group' => 'company', 'body' => <<<'MD'
                Every frame is covered for 365 days against manufacturing defects, including hinges, plating and lens coating delamination.
                Contact the concierge with your order number to start a claim.
                MD],
            ['slug' => 'terms', 'title' => 'Terms & Conditions', 'footer_group' => 'legal', 'body' => "These terms govern purchases from our online atelier. By placing an order you agree to them.\n\nPrices are shown in USD and include applicable taxes unless stated otherwise."],
            ['slug' => 'privacy', 'title' => 'Privacy Policy', 'footer_group' => 'legal', 'body' => "We collect only the data needed to fulfil your order and prescription.\n\nVisionMatch portraits are processed in your browser and never uploaded."],
        ];

        foreach ($pages as $i => $page) {
            Page::query()->updateOrCreate(['slug' => $page['slug']], [
                'title' => $page['title'],
                'footer_group' => $page['footer_group'],
                'body' => $page['body'],
                'is_published' => true,
                'sort_order' => $i,
            ]);
        }

        Settings::put([
            'social_instagram' => 'https://instagram.com/',
            'social_facebook' => 'https://facebook.com/',
            'social_youtube' => 'https://youtube.com/',
            'social_x' => '',
            'app_store_url' => '',
            'play_store_url' => '',
        ]);
    }
}
