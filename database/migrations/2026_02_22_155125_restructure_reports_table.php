<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->foreignId('incident_id')->after('user_id')->constrained()->cascadeOnDelete();
            $table->string('report_type')->after('incident_id');
            $table->unsignedInteger('sequence_number')->after('report_type');
            $table->foreignId('previous_report_id')->nullable()->after('sequence_number')->constrained('reports')->nullOnDelete();

            $table->dropIndex('reports_report_number_unique');
            $table->dropColumn('incident_name');

            $table->unique(['incident_id', 'city_municipality_id', 'sequence_number'], 'reports_incident_city_seq_unique');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['previous_report_id']);
            $table->dropUnique('reports_incident_city_seq_unique');

            $table->string('incident_name')->after('report_number');
            $table->unique('report_number');

            $table->dropForeign(['incident_id']);
            $table->dropColumn(['incident_id', 'report_type', 'sequence_number', 'previous_report_id']);
        });
    }
};
