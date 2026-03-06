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
        Schema::table('reports', function (Blueprint $table) {
            // V. Related Incidents
            $table->json('related_incidents')->nullable();

            // VI. Casualties (3 sub-arrays)
            $table->json('casualties_injured')->nullable();
            $table->json('casualties_missing')->nullable();
            $table->json('casualties_dead')->nullable();

            // VII. Damages to Infrastructure
            $table->json('infrastructure_damages')->nullable();

            // VIII. Damage & Losses to Agriculture
            $table->json('agriculture_damages')->nullable();

            // IX. Status of Assistance Provided
            $table->json('assistance_provided')->nullable();

            // X. Class Suspension
            $table->json('class_suspensions')->nullable();

            // XI. Work Suspension
            $table->json('work_suspensions')->nullable();

            // XII. Status of Lifelines (4 sub-arrays)
            $table->json('lifelines_roads_bridges')->nullable();
            $table->json('lifelines_power')->nullable();
            $table->json('lifelines_water')->nullable();
            $table->json('lifelines_communication')->nullable();

            // XIII. Status of Seaports
            $table->json('seaport_status')->nullable();

            // XIV. Status of Airports
            $table->json('airport_status')->nullable();

            // XV. Status of Landports
            $table->json('landport_status')->nullable();

            // XVI. Stranded Passengers/Cargoes
            $table->json('stranded_passengers')->nullable();

            // XVII. Declaration of State of Calamity
            $table->json('calamity_declarations')->nullable();

            // XVIII. Pre-emptive Evacuation
            $table->json('preemptive_evacuations')->nullable();

            // XIX. Gaps/Challenges
            $table->json('gaps_challenges')->nullable();

            // XX. Response Actions
            $table->text('response_actions')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn([
                'related_incidents',
                'casualties_injured',
                'casualties_missing',
                'casualties_dead',
                'infrastructure_damages',
                'agriculture_damages',
                'assistance_provided',
                'class_suspensions',
                'work_suspensions',
                'lifelines_roads_bridges',
                'lifelines_power',
                'lifelines_water',
                'lifelines_communication',
                'seaport_status',
                'airport_status',
                'landport_status',
                'stranded_passengers',
                'calamity_declarations',
                'preemptive_evacuations',
                'gaps_challenges',
                'response_actions',
            ]);
        });
    }
};
