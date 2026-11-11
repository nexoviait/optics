<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional 3D model of the frame (binary glTF, .glb). When present, the
     * product page's 360° view renders it instead of photos.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('model_3d_path')->nullable()->after('try_on_image');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('model_3d_path');
        });
    }
};
