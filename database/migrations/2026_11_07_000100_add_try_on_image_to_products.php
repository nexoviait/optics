<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional transparent front-view photo of the frame, overlaid on the
     * shopper's face in the AR try-on instead of the drawn frame.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('try_on_image')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('try_on_image');
        });
    }
};
