<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAdmin();

        $this->call([
            CatalogSeeder::class,
            $this->productSeeder(),
            ContentSeeder::class,
            PageSeeder::class,
        ]);

        // Sample customers and orders (all with the password "password") never belong on a live store.
        if (! app()->isProduction()) {
            $this->call(DemoDataSeeder::class);
        }
    }

    /** The frame catalogue to seed; the test suite swaps in its fixed demo frames. */
    protected function productSeeder(): string
    {
        return ProductSeeder::class;
    }

    /**
     * Create the bootstrap administrator. The password is only set when the
     * account is first created, so re-running seeders never resets it.
     */
    private function seedAdmin(): void
    {
        $admin = User::query()->firstOrNew(['email' => config('store.admin.email')]);

        if ($admin->exists) {
            $admin->forceFill(['role' => User::ROLE_ADMIN])->save();

            return;
        }

        $password = (string) config('store.admin.password');

        if (app()->isProduction() && (mb_strlen($password) < 12 || in_array($password, ['password', 'change-me'], true))) {
            throw new RuntimeException('Set a strong ADMIN_PASSWORD (12+ characters) in .env before seeding production.');
        }

        if ($password === '') {
            $password = 'password';
            $this->command?->warn("Admin created with the default password 'password' — change it under Admin → My account.");
        }

        $admin->fill(['name' => 'Atelier Admin', 'password' => $password]);
        $admin->forceFill(['role' => User::ROLE_ADMIN, 'email_verified_at' => now()])->save();
    }
}
