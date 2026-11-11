<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Find My Frames keeps only the estimated face shape and suggested size, never measurements or photos. */
    public function up(): void
    {
        Schema::table('vision_sessions', function (Blueprint $table) {
            $table->string('face_shape', 20)->nullable()->after('preferences');
            $table->string('size', 8)->nullable()->after('face_shape');
        });
    }

    public function down(): void
    {
        Schema::table('vision_sessions', function (Blueprint $table) {
            $table->dropColumn(['face_shape', 'size']);
        });
    }
};
