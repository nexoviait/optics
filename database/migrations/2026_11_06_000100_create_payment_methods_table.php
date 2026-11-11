<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Checkout payment methods move from config + a settings toggle into an
 * admin-managed table. Mobile banking payments keep the payer's account and
 * transaction ID so staff can verify them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 60);
            $table->string('type', 20);
            $table->string('description', 160)->nullable();
            $table->text('instructions')->nullable();
            $table->string('account_number', 40)->nullable();
            $table->string('logo_path')->nullable();
            $table->string('initial_status', 20)->default('Pending');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payer_account', 40)->nullable()->after('status');
            $table->string('payer_reference', 60)->nullable()->after('payer_account');
        });

        $now = now();
        $defaults = [
            ['card', 'Credit or Debit Card', 'card', null, null, null, 'Succeeded'],
            ['wallet', 'Apple Pay / Google Pay', 'wallet', null, null, null, 'Succeeded'],
            ['klarna', 'Klarna 4 installments', 'installment', null, null, null, 'Pending'],
            ['bkash', 'bKash', 'mobile', 'Pay with your bKash account', 'Send Money to the merchant number below, then enter your bKash number and the transaction ID (TrxID).', '01XXXXXXXXX', 'Pending'],
            ['nagad', 'Nagad', 'mobile', 'Pay with your Nagad account', 'Send Money to the merchant number below, then enter your Nagad number and the transaction ID.', '01XXXXXXXXX', 'Pending'],
            ['cod', 'Cash on Delivery', 'offline', 'Pay in cash when your order arrives', 'Please keep the exact amount ready for the courier.', null, 'Pending'],
        ];

        DB::table('payment_methods')->insert(collect($defaults)->map(fn (array $m, int $i) => [
            'code' => $m[0], 'name' => $m[1], 'type' => $m[2], 'description' => $m[3], 'instructions' => $m[4],
            'account_number' => $m[5], 'initial_status' => $m[6], 'is_active' => true, 'sort_order' => $i + 1,
            'created_at' => $now, 'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payer_account', 'payer_reference']);
        });

        Schema::dropIfExists('payment_methods');
    }
};
