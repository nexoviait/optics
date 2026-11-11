<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-product fine-tuning for the live camera try-on, so a model that
     * faces backwards or sits too big / too low is fixed in the admin, not in code.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('try_on_flip')->default(false)->after('model_3d_path'); // model faces backwards: turn it 180°
            $table->decimal('try_on_scale', 4, 2)->default(1)->after('try_on_flip'); // × the frame's real width
            $table->decimal('try_on_offset_y', 4, 1)->default(0)->after('try_on_scale'); // mm, + moves the frame up
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['try_on_flip', 'try_on_scale', 'try_on_offset_y']);
        });
    }
};
