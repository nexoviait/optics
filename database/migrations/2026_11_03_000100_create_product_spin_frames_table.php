<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordered photos taken around a frame on a turntable; played in sequence
     * they form the 360° view.
     */
    public function up(): void
    {
        Schema::create('product_spin_frames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('position');
            $table->timestamps();
            $table->index(['product_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_spin_frames');
    }
};
