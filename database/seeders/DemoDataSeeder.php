<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\VisionSession;
use App\Services\OrderPlacer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample clients and order history so the admin console has live data to show.
 * Orders go through the real OrderPlacer, so totals follow the pricing rules.
 */
class DemoDataSeeder extends Seeder
{
    public function run(OrderPlacer $placer): void
    {
        if (Order::query()->exists()) {
            return;
        }

        $clients = collect([
            ['Dr. Christian Vane', 'c.vane@mail.ch', 'Genève', 'Obsidian Circle'],
            ['Amélie Laurent', 'amelie@laurent.fr', 'Paris', 'Champagne Circle'],
            ['Kenji Watanabe', 'kenji@watanabe.jp', 'Tokyo', 'Sage Member'],
            ['Sofia Moretti', 'sofia@moretti.it', 'Milano', 'Sage Member'],
            ['James Whitmore', 'j.whitmore@uk.co', 'London', 'Obsidian Circle'],
            ['Nadia Rahman', 'nadia@rahman.bd', 'Dhaka', 'Champagne Circle'],
        ])->map(function (array $c) {
            $user = User::query()->firstOrNew(['email' => $c[1]]);
            $user->fill(['name' => $c[0], 'city' => $c[2], 'password' => 'password']);
            $user->forceFill(['role' => User::ROLE_CUSTOMER, 'tier' => $c[3], 'email_verified_at' => now()])->save();

            return $user;
        });

        $products = Product::query()->get();
        $openingStock = $products->pluck('stock', 'id');
        Product::query()->update(['stock' => 500]);
        $lensChoices = [
            ['power_type' => 'single', 'lens' => 'antiglare', 'rx_mode' => 'manual', 'rx' => ['name' => 'Patient', 'phone' => '+41 22 000 0000', 'r_sph' => '-2.75', 'r_cyl' => '-0.50', 'r_axis' => '180', 'l_sph' => '-3.00', 'l_cyl' => '-0.25', 'l_axis' => '170']],
            ['power_type' => 'zero', 'lens' => 'antiglare'],
            ['power_type' => 'progressive', 'lens' => 'photochromic', 'rx_mode' => 'later'],
            ['power_type' => 'single', 'lens' => 'highindex', 'rx_mode' => 'later'],
            null,
        ];
        $statuses = ['Delivered', 'Delivered', 'Delivered', 'Shipped', 'Ready to Ship', 'Processing', 'New'];
        $pipeline = config('store.order_pipeline');
        $addresses = [
            ['ship_phone' => '+41 22 310 4400', 'ship_line1' => 'Rue du Rhône 42', 'ship_city' => 'Genève', 'ship_postal' => '1204', 'ship_country' => 'CH'],
            ['ship_phone' => '+44 20 7946 0018', 'ship_line1' => '12 Marylebone High St', 'ship_city' => 'London', 'ship_postal' => 'W1U 4NR', 'ship_country' => 'GB'],
            ['ship_phone' => '+1 212 555 0147', 'ship_line1' => '88 Greene St', 'ship_line2' => 'Apt 4B', 'ship_city' => 'New York', 'ship_postal' => '10012', 'ship_country' => 'US'],
            ['ship_phone' => '+33 1 42 60 00 00', 'ship_line1' => '15 Rue Saint-Honoré', 'ship_city' => 'Paris', 'ship_postal' => '75001', 'ship_country' => 'FR'],
        ];
        $methods = ['card', 'wallet', 'klarna', 'card'];

        for ($i = 0; $i < 28; $i++) {
            $client = $clients[$i % $clients->count()];
            $lines = [];
            foreach ($products->random(rand(1, 2)) as $product) {
                $lines[] = [
                    'product_id' => $product->id,
                    'qty' => 1,
                    'finish' => $product->color,
                    'size' => ['S', 'M', 'L'][array_rand(['S', 'M', 'L'])],
                    'lens' => $lensChoices[array_rand($lensChoices)],
                ];
            }

            $address = $addresses[$i % count($addresses)];
            $order = $placer->place($client, $lines, $methods[$i % count($methods)], $i % 5 === 0 ? 'ATELIER10' : null, 'web', ['ship_name' => $client->name] + $address);
            $date = Carbon::now()->subDays(29 - $i)->setTime(rand(9, 20), rand(0, 59));
            // Older orders are fulfilled; the most recent ones are still moving through the pipeline.
            $status = $i < 21 ? 'Delivered' : $statuses[$i - 21];
            $reached = array_slice($pipeline, 0, array_search($status, $pipeline, true) + 1);
            $shipped = in_array('Shipped', $reached, true);

            $order->forceFill([
                'status' => $status,
                'created_at' => $date,
                'updated_at' => $date,
                'delivery_from' => $date->copy()->addWeekdays(5),
                'delivery_to' => $date->copy()->addWeekdays(8),
                'carrier' => $shipped ? 'DHL Express' : null,
                'tracking_number' => $shipped ? sprintf('JD01460%07d', 4800 + $i) : null,
                'shipped_at' => $shipped ? $date->copy()->addWeekdays(3) : null,
                'delivered_at' => $status === 'Delivered' ? $date->copy()->addWeekdays(6) : null,
            ])->saveQuietly();
            $order->payment?->forceFill(['created_at' => $date, 'updated_at' => $date])->saveQuietly();

            // Status history: one event per stage reached, a working day apart.
            foreach ($reached as $step => $stage) {
                $order->events()->create(['status' => $stage, 'note' => $step === 0 ? 'Order placed' : null])
                    ->forceFill(['created_at' => $date->copy()->addWeekdays($step)->min(now())])->save();
            }
        }

        // Restore catalog stock levels (a couple deliberately low to exercise alerts).
        foreach ($openingStock as $id => $stock) {
            Product::query()->whereKey($id)->update(['stock' => $stock]);
        }

        $reviews = [
            ['apex-01', 0, 5, 'Perfect fit', 'Impossibly light for a titanium frame and the fit was perfect straight from the box.'],
            ['apex-01', 4, 4, 'Beautiful finish', 'Took a day to adjust to the square silhouette, now I love it.'],
            ['aura-prism', 1, 5, 'True colours', 'The polarization is genuinely remarkable on the water — colors stay true.'],
            ['kinetics', 2, 5, 'Weightless', 'Forget you are wearing glasses. The rimless edges are flawless.'],
            ['havana-keyhole', 0, 5, 'Everyday classic', 'The tortoise pattern is gorgeous in person and the keyhole bridge sits comfortably all day.'],
            ['heritage-gold', 1, 5, 'Beautiful detailing', 'The brushed gold and little medallions feel genuinely premium. Nose pads make the fit perfect.'],
            ['rose-gradient', 3, 4, 'So pretty', 'The rosé fade is subtle and flattering — lighter than I expected.'],
            ['noir-sovereign', 4, 5, 'Real presence', 'Thick, glossy acetate and the gold rivets are a lovely touch.'],
        ];
        foreach ($reviews as [$slug, $clientIndex, $rating, $title, $body]) {
            // Only review frames present in the seeded catalogue.
            if (! $product = $products->firstWhere('slug', $slug)) {
                continue;
            }
            $client = $clients[$clientIndex];
            ProductReview::query()->firstOrCreate(
                ['user_id' => $client->id, 'product_id' => $product->id],
                ['reviewer_name' => $client->name, 'rating' => $rating, 'title' => $title, 'body' => $body],
            );
        }

        for ($i = 0; $i < 40; $i++) {
            $product = $products->random();
            VisionSession::query()->create([
                'preferences' => ['shape' => $product->shape],
                'top_product_id' => $product->id,
                'top_score' => rand(82, 99),
                'converted' => $i % 5 === 0,
            ]);
        }
    }
}
