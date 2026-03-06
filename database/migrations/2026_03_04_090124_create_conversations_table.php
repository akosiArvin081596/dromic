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
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index(); // 'group' or 'dm'
            $table->foreignId('province_id')->nullable()->constrained('provinces')->cascadeOnDelete();
            $table->string('dm_key')->nullable()->unique();
            $table->timestamps();

            $table->unique(['type', 'province_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
