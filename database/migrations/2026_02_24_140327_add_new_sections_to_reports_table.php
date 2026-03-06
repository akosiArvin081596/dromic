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
            $table->json('related_incidents')->default('[]');

            // VI. Casualties (3 sub-arrays)
            $table->json('casualties_injured')->default('[]');
            $table->json('casualties_missing')->default('[]');
            $table->json('casualties_dead')->default('[]');

            // VII. Damages to Infrastructure
            $table->json('infrastructure_damages')->default('[]');

            // VIII. Damage & Losses to Agriculture
            $table->json('agriculture_damages')->default('[]');

            // IX. Status of Assistance Provided
            $table->json('assistance_provided')->default('[]');

            // X. Class Suspension
            $table->json('class_suspensions')->default('[]');

            // XI. Work Suspension
            $table->json('work_suspensions')->default('[]');

            // XII. Status of Lifelines (4 sub-arrays)
            $table->json('lifelines_roads_bridges')->default('[]');
            $table->json('lifelines_power')->default('[]');
            $table->json('lifelines_water')->default('[]');
            $table->json('lifelines_communication')->default('[]');

            // XIII. Status of Seaports
            $table->json('seaport_status')->default('[]');

            // XIV. Status of Airports
            $table->json('airport_status')->default('[]');

            // XV. Status of Landports
            $table->json('landport_status')->default('[]');

            // XVI. Stranded Passengers/Cargoes
            $table->json('stranded_passengers')->default('[]');

            // XVII. Declaration of State of Calamity
            $table->json('calamity_declarations')->default('[]');

            // XVIII. Pre-emptive Evacuation
            $table->json('preemptive_evacuations')->default('[]');

            // XIX. Gaps/Challenges
            $table->json('gaps_challenges')->default('[]');

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
