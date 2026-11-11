<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('series')->nullable();
            $table->string('badge')->nullable();
            $table->string('category', 20)->index();
            $table->decimal('price', 10, 2);
            $table->decimal('cost', 10, 2)->default(0);
            $table->string('sku')->unique();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(10);
            $table->string('status', 20)->default('active')->index();
            $table->string('image')->nullable();
            $table->string('shape')->nullable();
            $table->string('color')->nullable();
            $table->string('material')->nullable();
            $table->string('lens_tag')->nullable();
            $table->string('weight', 20)->nullable();
            $table->json('finishes')->nullable();
            $table->text('description')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('frame_sizes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 4)->unique();
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('lens_width');
            $table->unsignedSmallInteger('bridge');
            $table->unsignedSmallInteger('temple');
            $table->string('face', 60);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('power_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('needs_lens')->default(true);
            $table->boolean('needs_rx')->default(true);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lens_options', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('label');
            $table->string('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lens_skus', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('substrate', 40);
            $table->string('refractive_index', 8);
            $table->unsignedSmallInteger('abbe');
            $table->string('geometry', 60);
            $table->json('coatings')->nullable();
            $table->json('labs')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('low_stock_threshold')->default(50);
            $table->timestamps();
        });

        Schema::create('shipping_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('courier');
            $table->json('countries');
            $table->unsignedTinyInteger('transit_min');
            $table->unsignedTinyInteger('transit_max');
            $table->string('rate_label', 30)->nullable();
            $table->json('holidays')->nullable();
            $table->boolean('enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 10)->default('percent');
            $table->decimal('value', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupons');
        Schema::dropIfExists('shipping_zones');
        Schema::dropIfExists('lens_skus');
        Schema::dropIfExists('lens_options');
        Schema::dropIfExists('power_types');
        Schema::dropIfExists('frame_sizes');
        Schema::dropIfExists('products');
    }
};
