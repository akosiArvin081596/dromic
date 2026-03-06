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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_number')->unique();
            $table->string('incident_name');
            $table->foreignId('city_municipality_id')->constrained()->cascadeOnDelete();
            $table->date('report_date');
            $table->string('report_time');
            $table->text('situation_overview')->nullable();
            $table->json('affected_areas');
            $table->json('inside_evacuation_centers');
            $table->json('age_distribution');
            $table->json('vulnerable_sectors');
            $table->json('outside_evacuation_centers');
            $table->json('damaged_houses');
            $table->string('status')->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
