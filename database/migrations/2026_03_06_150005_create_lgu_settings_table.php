<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lgu_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('city_municipality_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('signatory_1_name')->nullable();
            $table->string('signatory_1_designation')->nullable();
            $table->string('signatory_2_name')->nullable();
            $table->string('signatory_2_designation')->nullable();
            $table->string('signatory_3_name')->nullable();
            $table->string('signatory_3_designation')->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lgu_settings');
    }
};
