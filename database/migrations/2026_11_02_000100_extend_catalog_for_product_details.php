<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('brand', 80)->nullable()->after('name')->index();
            $table->string('model_no', 40)->nullable()->after('brand');
            $table->string('gender', 20)->default('unisex')->after('category')->index();
            $table->string('age_group', 30)->nullable()->after('gender');
            $table->string('frame_type', 20)->default('full_rim')->after('age_group')->index();
            $table->string('temple_material', 80)->nullable()->after('material');
            $table->decimal('compare_at_price', 10, 2)->nullable()->after('price');
            $table->string('offer_text', 120)->nullable()->after('compare_at_price');
            // Products sharing a variant group are colour variants of the same frame.
            $table->string('variant_group', 60)->nullable()->after('slug')->index();
            $table->string('color_name', 40)->nullable()->after('color');
        });

        Schema::create('product_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('alt')->nullable();
            // Lifestyle images appear in the "Style Stories" carousel instead of the main gallery.
            $table->boolean('is_lifestyle')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['product_id', 'is_lifestyle', 'sort_order']);
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::table('lens_options', function (Blueprint $table) {
            $table->json('features')->nullable()->after('description');
            $table->string('badge', 40)->nullable()->after('features');
        });

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->string('title', 120)->nullable()->after('rating');
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_description')->nullable();
            $table->longText('body');
            $table->string('footer_group', 30)->nullable();
            $table->boolean('is_published')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');

        Schema::table('product_reviews', function (Blueprint $table) {
            $table->dropColumn('title');
        });

        Schema::table('lens_options', function (Blueprint $table) {
            $table->dropColumn(['features', 'badge']);
        });

        Schema::dropIfExists('wishlist_items');
        Schema::dropIfExists('product_images');

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['brand']);
            $table->dropIndex(['gender']);
            $table->dropIndex(['frame_type']);
            $table->dropIndex(['variant_group']);
            $table->dropColumn([
                'brand', 'model_no', 'gender', 'age_group', 'frame_type', 'temple_material',
                'compare_at_price', 'offer_text', 'variant_group', 'color_name',
            ]);
        });
    }
};
