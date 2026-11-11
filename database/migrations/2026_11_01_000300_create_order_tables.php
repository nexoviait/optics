<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source', 10)->default('manual');
            $table->string('patient_name', 80)->nullable();
            $table->string('phone', 20)->nullable();
            foreach (['r_sph', 'r_cyl', 'r_axis', 'l_sph', 'l_cyl', 'l_axis', 'add_power', 'pd'] as $column) {
                $table->string($column, 12)->nullable();
            }
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('New')->index();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('member_discount', 10, 2)->default(0);
            $table->string('coupon_code', 40)->nullable();
            $table->decimal('gift_discount', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('payment_method', 20);
            $table->string('channel', 20)->default('web');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('finish', 80)->nullable();
            $table->string('frame_size', 4)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->unsignedSmallInteger('qty');
            $table->string('power_type', 30)->nullable();
            $table->string('lens', 30)->nullable();
            $table->string('rx_mode', 10)->nullable();
            $table->foreignId('prescription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 30)->unique();
            $table->string('method', 20);
            $table->decimal('amount', 10, 2);
            $table->decimal('fee', 10, 2)->default(0);
            $table->string('status', 20)->default('Pending')->index();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('reviewer_name', 60);
            $table->unsignedTinyInteger('rating');
            $table->text('body');
            $table->boolean('verified_buyer')->default(false);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
            $table->index(['product_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('prescriptions');
    }
};
