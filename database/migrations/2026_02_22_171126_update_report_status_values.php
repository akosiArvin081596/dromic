<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reports')->where('status', 'submitted')->update(['status' => 'for_validation']);
        DB::table('reports')->where('status', 'finalized')->update(['status' => 'validated']);
    }

    public function down(): void
    {
        DB::table('reports')->where('status', 'for_validation')->update(['status' => 'submitted']);
        DB::table('reports')->where('status', 'validated')->update(['status' => 'finalized']);
    }
};
