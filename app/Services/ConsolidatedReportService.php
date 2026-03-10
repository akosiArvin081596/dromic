<?php

namespace App\Services;

use App\Enums\IncidentType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Report;
use Illuminate\Support\Collection;

class ConsolidatedReportService
{
    /**
     * @return array{cutoffs: array<int, array{label: string, date: string, time: string, reports: Collection<int, Report>, totals: array<string, mixed>}>}
     */
    public function consolidateByCutoff(Incident $incident, ?int $provinceId = null, ?int $regionId = null, bool $validatedOnly = false): array
    {
        $query = Report::query()
            ->where('incident_id', $incident->id)
            ->whereIn('status', $validatedOnly ? ['validated'] : ['for_validation', 'validated'])
            ->with('cityMunicipality.province', 'user');

        if ($provinceId) {
            $query->whereHas('cityMunicipality', function ($q) use ($provinceId) {
                $q->where('province_id', $provinceId);
            });
        } elseif ($regionId) {
            $query->whereHas('cityMunicipality', function ($q) use ($regionId) {
                $q->whereHas('province', fn ($pq) => $pq->where('region_id', $regionId));
            });
        }

        $allReports = $query->get();

        // For massive incidents, all LGUs in the user's scope are concerned.
        // For local incidents, use the pivot table.
        if ($incident->type === IncidentType::Massive) {
            $assignedLguIds = CityMunicipality::query()
                ->when($provinceId, fn ($q) => $q->where('province_id', $provinceId))
                ->when($regionId, fn ($q) => $q->whereHas('province', fn ($pq) => $pq->where('region_id', $regionId)))
                ->pluck('id')
                ->all();
        } else {
            $assignedLguIds = $incident->cityMunicipalities()
                ->when($provinceId, fn ($q) => $q->where('province_id', $provinceId))
                ->when($regionId, fn ($q) => $q->whereHas('province', fn ($pq) => $pq->where('region_id', $regionId)))
                ->pluck('city_municipalities.id')
                ->all();
        }

        // Determine unique cut-off periods sorted chronologically
        // report_date is cast to Carbon, so use toDateString() for a clean Y-m-d key
        $cutoffKey = fn (Report $r): string => $r->report_date->toDateString() . '|' . $r->report_time;

        $cutoffKeys = $allReports
            ->map($cutoffKey)
            ->unique()
            ->sort(function (string $a, string $b) {
                [$dateA, $timeA] = explode('|', $a);
                [$dateB, $timeB] = explode('|', $b);
                $cmp = $dateA <=> $dateB;

                return $cmp !== 0 ? $cmp : ($timeA === '12:00 AM' ? -1 : 1);
            })
            ->values();

        // Group reports by cut-off key, then by LGU
        $reportsByCutoff = $allReports->groupBy($cutoffKey);

        $labels = ['Initial Report'];
        $prCounter = 1;
        foreach ($cutoffKeys->slice(1) as $ignored) {
            $labels[] = 'Progress Report No. ' . $prCounter++;
        }

        /** @var array<int, Report|null> $carryForward latest report per LGU across previous cut-offs */
        $carryForward = [];

        $cutoffs = [];
        foreach ($cutoffKeys as $index => $key) {
            [$date, $time] = explode('|', $key);

            $cutoffReports = $reportsByCutoff->get($key, collect());
            // When multiple reports exist for the same LGU in a cut-off, use the latest by sequence_number
            $reportsByLgu = $cutoffReports
                ->groupBy('city_municipality_id')
                ->map(fn (Collection $lguReports) => $lguReports->sortByDesc('sequence_number')->first());

            $effectiveReports = collect();
            foreach ($assignedLguIds as $lguId) {
                if ($reportsByLgu->has($lguId)) {
                    $report = $reportsByLgu->get($lguId);
                    $report->carried_forward = false;
                    $effectiveReports->push($report);
                    $carryForward[$lguId] = $report;
                } elseif (isset($carryForward[$lguId])) {
                    $carried = clone $carryForward[$lguId];
                    $carried->carried_forward = true;
                    $effectiveReports->push($carried);
                }
            }

            $totals = $this->aggregateTotals($effectiveReports);

            $cutoffs[] = [
                'label' => $labels[$index],
                'date' => $date,
                'time' => $time,
                'reports' => $effectiveReports->values(),
                'totals' => $totals,
            ];
        }

        return ['cutoffs' => $cutoffs];
    }

    /**
     * @return array{reports: Collection<int, Report>, totals: array<string, mixed>}
     */
    public function consolidate(Incident $incident, ?int $provinceId = null, ?int $regionId = null, bool $validatedOnly = false): array
    {
        $query = Report::query()
            ->where('incident_id', $incident->id)
            ->whereIn('status', $validatedOnly ? ['validated'] : ['for_validation', 'validated'])
            ->with('cityMunicipality.province', 'user');

        if ($provinceId) {
            $query->whereHas('cityMunicipality', function ($q) use ($provinceId) {
                $q->where('province_id', $provinceId);
            });
        } elseif ($regionId) {
            $query->whereHas('cityMunicipality', function ($q) use ($regionId) {
                $q->whereHas('province', fn ($pq) => $pq->where('region_id', $regionId));
            });
        }

        // Get the latest report per LGU
        $allReports = $query->orderByDesc('sequence_number')->get();

        $latestPerLgu = $allReports->groupBy('city_municipality_id')->map(function (Collection $reports) {
            return $reports->first();
        })->values();

        $totals = $this->aggregateTotals($latestPerLgu);

        return [
            'reports' => $latestPerLgu,
            'totals' => $totals,
        ];
    }

    /**
     * @param Collection<int, Report> $reports
     *
     * @return array<string, mixed>
     */
    private function aggregateTotals(Collection $reports): array
    {
        $totalAffectedFamilies = 0;
        $totalAffectedPersons = 0;
        $totalInsideECFamiliesCum = 0;
        $totalInsideECFamiliesNow = 0;
        $totalInsideECPersonsCum = 0;
        $totalInsideECPersonsNow = 0;
        $totalOutsideECFamiliesCum = 0;
        $totalOutsideECFamiliesNow = 0;
        $totalOutsideECPersonsCum = 0;
        $totalOutsideECPersonsNow = 0;
        $totalNonIdpFamiliesCum = 0;
        $totalNonIdpPersonsCum = 0;
        $totalTotallyDamaged = 0;
        $totalPartiallyDamaged = 0;
        $totalEstimatedCost = 0;
        $totalCasualtiesInjured = 0;
        $totalCasualtiesMissing = 0;
        $totalCasualtiesDead = 0;
        $totalInfrastructureCost = 0;
        $totalAgricultureCost = 0;
        $totalStrandedPassengers = 0;
        $totalPreemptiveFamilies = 0;
        $totalPreemptivePersons = 0;

        foreach ($reports as $report) {
            foreach ($report->affected_areas ?? [] as $area) {
                $totalAffectedFamilies += $area['families'] ?? 0;
                $totalAffectedPersons += $area['persons'] ?? 0;
            }

            foreach ($report->inside_evacuation_centers ?? [] as $ec) {
                $totalInsideECFamiliesCum += $ec['families_cum'] ?? 0;
                $totalInsideECFamiliesNow += $ec['families_now'] ?? 0;
                $totalInsideECPersonsCum += $ec['persons_cum'] ?? 0;
                $totalInsideECPersonsNow += $ec['persons_now'] ?? 0;
            }

            foreach ($report->outside_evacuation_centers ?? [] as $ec) {
                $totalOutsideECFamiliesCum += $ec['families_cum'] ?? 0;
                $totalOutsideECFamiliesNow += $ec['families_now'] ?? 0;
                $totalOutsideECPersonsCum += $ec['persons_cum'] ?? 0;
                $totalOutsideECPersonsNow += $ec['persons_now'] ?? 0;
            }

            foreach ($report->non_idps ?? [] as $nonIdp) {
                $totalNonIdpFamiliesCum += $nonIdp['families_cum'] ?? 0;
                $totalNonIdpPersonsCum += $nonIdp['persons_cum'] ?? 0;
            }

            foreach ($report->damaged_houses ?? [] as $house) {
                $totalTotallyDamaged += $house['totally_damaged'] ?? 0;
                $totalPartiallyDamaged += $house['partially_damaged'] ?? 0;
                $totalEstimatedCost += $house['estimated_cost'] ?? 0;
            }

            $totalCasualtiesInjured += count($report->casualties_injured ?? []);
            $totalCasualtiesMissing += count($report->casualties_missing ?? []);
            $totalCasualtiesDead += count($report->casualties_dead ?? []);

            foreach ($report->infrastructure_damages ?? [] as $infra) {
                $totalInfrastructureCost += $infra['estimated_cost'] ?? 0;
            }

            foreach ($report->agriculture_damages ?? [] as $agri) {
                $totalAgricultureCost += $agri['estimated_cost'] ?? 0;
            }

            foreach ($report->stranded_passengers ?? [] as $strand) {
                $totalStrandedPassengers += $strand['passengers'] ?? 0;
            }

            foreach ($report->preemptive_evacuations ?? [] as $preempt) {
                $totalPreemptiveFamilies += $preempt['families'] ?? 0;
                $totalPreemptivePersons += $preempt['persons'] ?? 0;
            }
        }

        return [
            'affected_families' => $totalAffectedFamilies,
            'affected_persons' => $totalAffectedPersons,
            'inside_ec_families_cum' => $totalInsideECFamiliesCum,
            'inside_ec_families_now' => $totalInsideECFamiliesNow,
            'inside_ec_persons_cum' => $totalInsideECPersonsCum,
            'inside_ec_persons_now' => $totalInsideECPersonsNow,
            'outside_ec_families_cum' => $totalOutsideECFamiliesCum,
            'outside_ec_families_now' => $totalOutsideECFamiliesNow,
            'outside_ec_persons_cum' => $totalOutsideECPersonsCum,
            'outside_ec_persons_now' => $totalOutsideECPersonsNow,
            'non_idp_families_cum' => $totalNonIdpFamiliesCum,
            'non_idp_persons_cum' => $totalNonIdpPersonsCum,
            'totally_damaged' => $totalTotallyDamaged,
            'partially_damaged' => $totalPartiallyDamaged,
            'estimated_cost' => $totalEstimatedCost,
            'casualties_injured' => $totalCasualtiesInjured,
            'casualties_missing' => $totalCasualtiesMissing,
            'casualties_dead' => $totalCasualtiesDead,
            'infrastructure_cost' => $totalInfrastructureCost,
            'agriculture_cost' => $totalAgricultureCost,
            'stranded_passengers' => $totalStrandedPassengers,
            'preemptive_families' => $totalPreemptiveFamilies,
            'preemptive_persons' => $totalPreemptivePersons,
        ];
    }
}
