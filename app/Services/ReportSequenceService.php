<?php

namespace App\Services;

use App\Enums\ReportType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Report;
use RuntimeException;

class ReportSequenceService
{
    /**
     * @return array{type: ReportType, sequence: int, previous_report_id: int|null}
     */
    public function getNextReportInfo(int $incidentId, int $cityMunicipalityId): array
    {
        $latestReport = Report::query()
            ->forIncidentAndLgu($incidentId, $cityMunicipalityId)
            ->orderByDesc('sequence_number')
            ->first();

        if (! $latestReport) {
            return [
                'type' => ReportType::Initial,
                'sequence' => 0,
                'previous_report_id' => null,
            ];
        }

        if ($latestReport->report_type === ReportType::Terminal) {
            throw new RuntimeException('A terminal report has already been filed for this incident and LGU. No more reports are allowed.');
        }

        return [
            'type' => ReportType::Progress,
            'sequence' => $latestReport->sequence_number + 1,
            'previous_report_id' => $latestReport->id,
        ];
    }

    public function generateReportNumber(Incident $incident, CityMunicipality $lgu, ReportType $type, int $sequence): string
    {
        $psgc = $lgu->psgc_code;

        return match ($type) {
            ReportType::Initial => "DROMIC-{$incident->id}-{$psgc}-IR",
            ReportType::Progress => "DROMIC-{$incident->id}-{$psgc}-PR{$sequence}",
            ReportType::Terminal => "DROMIC-{$incident->id}-{$psgc}-TR",
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function copyFromPrevious(Report $report): array
    {
        return [
            'situation_overview' => $report->situation_overview,
            'affected_areas' => $report->affected_areas,
            'inside_evacuation_centers' => $report->inside_evacuation_centers,
            'age_distribution' => $report->age_distribution,
            'vulnerable_sectors' => $report->vulnerable_sectors,
            'outside_evacuation_centers' => $report->outside_evacuation_centers,
            'non_idps' => $report->non_idps,
            'damaged_houses' => $report->damaged_houses,
            'related_incidents' => $report->related_incidents,
            'casualties_injured' => $report->casualties_injured,
            'casualties_missing' => $report->casualties_missing,
            'casualties_dead' => $report->casualties_dead,
            'infrastructure_damages' => $report->infrastructure_damages,
            'agriculture_damages' => $report->agriculture_damages,
            'assistance_provided' => $report->assistance_provided,
            'class_suspensions' => $report->class_suspensions,
            'work_suspensions' => $report->work_suspensions,
            'lifelines_roads_bridges' => $report->lifelines_roads_bridges,
            'lifelines_power' => $report->lifelines_power,
            'lifelines_water' => $report->lifelines_water,
            'lifelines_communication' => $report->lifelines_communication,
            'seaport_status' => $report->seaport_status,
            'airport_status' => $report->airport_status,
            'landport_status' => $report->landport_status,
            'stranded_passengers' => $report->stranded_passengers,
            'calamity_declarations' => $report->calamity_declarations,
            'preemptive_evacuations' => $report->preemptive_evacuations,
            'gaps_challenges' => $report->gaps_challenges,
            'response_actions' => $report->response_actions,
        ];
    }
}
