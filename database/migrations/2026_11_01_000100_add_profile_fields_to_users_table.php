<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('password')->index();
            $table->string('phone', 30)->nullable()->after('role');
            $table->string('city', 80)->nullable()->after('phone');
            $table->string('tier', 40)->default('Sage Member')->after('city');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn(['role', 'phone', 'city', 'tier']);
        });
    }
};
