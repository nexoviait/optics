<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * No payment gateway is connected: card and wallet orders were recorded as paid
 * without any money moving, and bKash/Nagad still showed a placeholder merchant
 * number. Switch them off until they are set up (Admin → Payments → Methods).
 */
return new class extends Migration
{
    public function up(): void
    {
        $methods = DB::table('payment_methods');
        $now = now();

        (clone $methods)->whereIn('code', ['card', 'wallet'])
            ->update(['is_active' => false, 'initial_status' => 'Pending', 'updated_at' => $now]);

        (clone $methods)->where('code', 'klarna')
            ->update(['is_active' => false, 'updated_at' => $now]);

        (clone $methods)->whereIn('code', ['bkash', 'nagad'])->where('account_number', 'like', '%XXX%')
            ->update(['is_active' => false, 'updated_at' => $now]);

        // Checkout always needs one way to pay.
        if (! (clone $methods)->where('is_active', true)->exists()) {
            (clone $methods)->where('code', 'cod')->update(['is_active' => true, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Payment methods are managed in the admin; nothing to restore automatically.
    }
};
