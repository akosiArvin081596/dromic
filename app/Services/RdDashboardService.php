<?php

namespace App\Services;

use App\Enums\RequestLetterStatus;
use App\Models\Incident;
use Illuminate\Support\Collection;

class RdDashboardService
{
    public function __construct(
        private ConsolidatedReportService $consolidatedReportService,
        private RequestLetterLedgerService $ledgerService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getImpactSummary(Incident $incident, int $regionId): array
    {
        $consolidated = $this->consolidatedReportService->consolidate($incident, regionId: $regionId, validatedOnly: true);
        $totals = $consolidated['totals'];

        $activeEcCount = 0;
        $totalEcCount = 0;
        $evacuationCenters = [];
        foreach ($consolidated['reports'] as $report) {
            $lguName = $report->cityMunicipality?->name ?? '';
            $provinceName = $report->cityMunicipality?->province?->name ?? '';

            foreach ($report->inside_evacuation_centers ?? [] as $ec) {
                if (($ec['families_cum'] ?? 0) > 0 || ($ec['families_now'] ?? 0) > 0) {
                    $totalEcCount++;
                    $isActive = ($ec['families_now'] ?? 0) > 0;
                    if ($isActive) {
                        $activeEcCount++;
                    }
                    $evacuationCenters[] = [
                        'ec_name' => $ec['ec_name'] ?? '',
                        'barangay' => $ec['barangay'] ?? '',
                        'city_municipality' => $lguName,
                        'province' => $provinceName,
                        'families_cum' => $ec['families_cum'] ?? 0,
                        'families_now' => $ec['families_now'] ?? 0,
                        'persons_cum' => $ec['persons_cum'] ?? 0,
                        'persons_now' => $ec['persons_now'] ?? 0,
                        'status' => $isActive ? 'active' : 'closed',
                        'remarks' => $ec['remarks'] ?? '',
                    ];
                }
            }
        }

        $closedEcCount = $totalEcCount - $activeEcCount;
        $closedEcPercent = $totalEcCount > 0
            ? round(($closedEcCount / $totalEcCount) * 100, 1)
            : 0;

        return [
            ...$totals,
            'active_evacuation_centers' => $activeEcCount,
            'total_evacuation_centers' => $totalEcCount,
            'closed_evacuation_centers' => $closedEcCount,
            'closed_ec_percent' => $closedEcPercent,
            'evacuation_centers' => $evacuationCenters,
        ];
    }

    /**
     * @return array<int, array{province_id: int, province_name: string, items: array<int, array{type: string, approved: int, delivered: int, delivery_percent: float}>, lgus: array<int, array{city_municipality_id: int, city_municipality_name: string, items: array<int, array{type: string, approved: int, delivered: int, delivery_percent: float}>}>}>
     */
    public function getAugmentationSummary(Incident $incident, int $regionId): array
    {
        $requestLetters = $incident->requestLetters()
            ->whereIn('status', [
                RequestLetterStatus::Approved,
                RequestLetterStatus::Delivering,
                RequestLetterStatus::Completed,
            ])
            ->whereHas('cityMunicipality', function ($q) use ($regionId) {
                $q->whereHas('province', fn ($pq) => $pq->where('region_id', $regionId));
            })
            ->with(['cityMunicipality.province', 'deliveries'])
            ->get();

        /** @var Collection<int, array{province_id: int, province_name: string, lgus: Collection<int, array{city_municipality_id: int, city_municipality_name: string, items: array<int, array{type: string, approved: int, delivered: int}>}>}> $provinces */
        $provinces = collect();

        foreach ($requestLetters as $requestLetter) {
            $cm = $requestLetter->cityMunicipality;
            $province = $cm?->province;

            if (! $province) {
                continue;
            }

            $ledger = $this->ledgerService->getLedger($requestLetter);

            $lguItems = collect($ledger)->map(fn (array $item) => [
                'type' => $item['type'],
                'approved' => $item['approved'] ?? $item['endorsed'] ?? $item['requested'],
                'delivered' => $item['delivered'],
            ])->all();

            if (! $provinces->has($province->id)) {
                $provinces[$province->id] = [
                    'province_id' => $province->id,
                    'province_name' => $province->name,
                    'lgus' => collect(),
                ];
            }

            $provinceData = $provinces[$province->id];
            $existingLgu = $provinceData['lgus']->firstWhere('city_municipality_id', $cm->id);

            if ($existingLgu) {
                // Merge items from multiple request letters for the same LGU
                $mergedItems = collect($existingLgu['items']);
                foreach ($lguItems as $newItem) {
                    $existing = $mergedItems->firstWhere('type', $newItem['type']);
                    if ($existing) {
                        $mergedItems = $mergedItems->map(function ($item) use ($newItem) {
                            if ($item['type'] === $newItem['type']) {
                                $item['approved'] += $newItem['approved'];
                                $item['delivered'] += $newItem['delivered'];
                            }

                            return $item;
                        });
                    } else {
                        $mergedItems->push($newItem);
                    }
                }
                $provinceData['lgus'] = $provinceData['lgus']->map(function ($lgu) use ($cm, $mergedItems) {
                    if ($lgu['city_municipality_id'] === $cm->id) {
                        $lgu['items'] = $mergedItems->values()->all();
                    }

                    return $lgu;
                });
            } else {
                $provinceData['lgus']->push([
                    'city_municipality_id' => $cm->id,
                    'city_municipality_name' => $cm->name,
                    'items' => $lguItems,
                ]);
            }

            $provinces[$province->id] = $provinceData;
        }

        // Aggregate province-level totals and compute delivery percentages
        return $provinces->map(function ($provinceData) {
            $provinceTotals = collect();

            foreach ($provinceData['lgus'] as $lgu) {
                foreach ($lgu['items'] as $item) {
                    $existing = $provinceTotals->firstWhere('type', $item['type']);
                    if ($existing) {
                        $provinceTotals = $provinceTotals->map(function ($t) use ($item) {
                            if ($t['type'] === $item['type']) {
                                $t['approved'] += $item['approved'];
                                $t['delivered'] += $item['delivered'];
                            }

                            return $t;
                        });
                    } else {
                        $provinceTotals->push([
                            'type' => $item['type'],
                            'approved' => $item['approved'],
                            'delivered' => $item['delivered'],
                        ]);
                    }
                }
            }

            $addPercent = fn (array $item) => [
                ...$item,
                'delivery_percent' => $item['approved'] > 0
                    ? round(($item['delivered'] / $item['approved']) * 100, 1)
                    : 0,
            ];

            return [
                'province_id' => $provinceData['province_id'],
                'province_name' => $provinceData['province_name'],
                'items' => $provinceTotals->map($addPercent)->values()->all(),
                'lgus' => $provinceData['lgus']->map(function ($lgu) use ($addPercent) {
                    $lgu['items'] = array_map($addPercent, $lgu['items']);

                    return $lgu;
                })->values()->all(),
            ];
        })->values()->all();
    }
}
