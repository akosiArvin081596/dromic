<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('request_letters', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('augmentation_items');
            $table->foreignId('endorsed_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('endorsed_at')->nullable()->after('endorsed_by');
            $table->foreignId('approved_by')->nullable()->after('endorsed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('request_letters', function (Blueprint $table) {
            $table->dropForeign(['endorsed_by']);
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['status', 'endorsed_by', 'endorsed_at', 'approved_by', 'approved_at']);
        });
    }
};
