<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('user_type')->nullable()->after('role');
        });

        DB::table('users')->where('role', 'lgu')->update(['user_type' => 'cmswdo']);
        DB::table('users')->where('role', 'provincial')->update(['user_type' => 'provincial_dswd']);
        DB::table('users')->where('role', 'regional')->update(['user_type' => 'drims']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('user_type');
        });
    }
};
