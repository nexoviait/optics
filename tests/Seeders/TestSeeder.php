<?php

namespace Tests\Seeders;

use App\Models\PaymentMethod;
use Database\Seeders\DatabaseSeeder;

/** The full application seed, with the test suite's fixed demo frames as the catalogue. */
class TestSeeder extends DatabaseSeeder
{
    public function run(): void
    {
        // The store ships with these off until they are set up; the suite exercises every checkout type.
        PaymentMethod::query()->update(['is_active' => true]);
        PaymentMethod::query()->whereIn('code', ['card', 'wallet'])->update(['initial_status' => 'Succeeded']);

        parent::run();
    }

    protected function productSeeder(): string
    {
        return DemoProductSeeder::class;
    }
}
