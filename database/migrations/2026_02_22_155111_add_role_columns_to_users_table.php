<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('lgu')->after('remember_token');
            $table->foreignId('province_id')->nullable()->after('role')->constrained('provinces')->nullOnDelete();
            $table->foreignId('city_municipality_id')->nullable()->after('province_id')->constrained('city_municipalities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['city_municipality_id']);
            $table->dropForeign(['province_id']);
            $table->dropColumn(['role', 'province_id', 'city_municipality_id']);
        });
    }
};
