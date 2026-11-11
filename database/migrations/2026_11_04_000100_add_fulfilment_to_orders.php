<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ship_name', 80)->nullable()->after('channel');
            $table->string('ship_phone', 30)->nullable()->after('ship_name');
            $table->string('ship_line1', 160)->nullable()->after('ship_phone');
            $table->string('ship_line2', 160)->nullable()->after('ship_line1');
            $table->string('ship_city', 80)->nullable()->after('ship_line2');
            $table->string('ship_postal', 12)->nullable()->after('ship_city');
            $table->string('ship_country', 5)->nullable()->after('ship_postal');
            $table->string('customer_note', 500)->nullable()->after('ship_country');
            $table->string('carrier', 40)->nullable()->after('customer_note');
            $table->string('tracking_number', 60)->nullable()->after('carrier');
            $table->date('delivery_from')->nullable()->after('tracking_number');
            $table->date('delivery_to')->nullable()->after('delivery_from');
            $table->timestamp('shipped_at')->nullable()->after('delivery_to');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
            $table->timestamp('cancelled_at')->nullable()->after('delivered_at');
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('note', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'ship_name', 'ship_phone', 'ship_line1', 'ship_line2', 'ship_city', 'ship_postal', 'ship_country',
                'customer_note', 'carrier', 'tracking_number', 'delivery_from', 'delivery_to',
                'shipped_at', 'delivered_at', 'cancelled_at',
            ]);
        });
    }
};
