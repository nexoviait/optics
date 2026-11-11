<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guest checkout: orders and prescriptions may exist without an account, and
 * every order keeps its own contact email for notifications and tracking.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('email')->nullable()->after('user_id')->index();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });

        // Existing orders all belong to accounts: copy the account email across.
        DB::table('orders')->whereNull('email')->update([
            'email' => DB::raw('(SELECT users.email FROM users WHERE users.id = orders.user_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropColumn('email');
        });
    }
};
