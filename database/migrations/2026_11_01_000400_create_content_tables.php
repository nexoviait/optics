<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('page', 40)->default('home');
            $table->string('key', 40);
            $table->string('label');
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->json('content')->nullable();
            $table->timestamps();
            $table->unique(['page', 'key']);
        });

        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('hook', 60);
            $table->string('category', 30);
            $table->string('locale', 10)->default('en-GB');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->json('blocks')->nullable();
            $table->string('status', 10)->default('Draft');
            $table->timestamps();
        });

        Schema::create('vision_steps', function (Blueprint $table) {
            $table->id();
            $table->string('key', 30)->nullable();
            $table->string('title');
            $table->string('options');
            $table->boolean('is_required')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('vision_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('preferences')->nullable();
            $table->foreignId('top_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedTinyInteger('top_score')->nullable();
            $table->boolean('converted')->default(false);
            $table->timestamps();
        });

        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 80)->nullable();
            $table->string('email')->nullable();
            $table->date('preferred_date')->nullable();
            $table->json('product_ids')->nullable();
            $table->string('status', 20)->default('New');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('service_requests');
        Schema::dropIfExists('newsletter_subscribers');
        Schema::dropIfExists('vision_sessions');
        Schema::dropIfExists('vision_steps');
        Schema::dropIfExists('email_templates');
        Schema::dropIfExists('page_sections');
        Schema::dropIfExists('settings');
    }
};
