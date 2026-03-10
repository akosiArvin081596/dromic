<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Services\ConsolidatedReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DisplayBoardController extends Controller
{
    public function __construct(private ConsolidatedReportService $consolidatedService) {}

    public function __invoke(Request $request, ?Incident $incident = null): Response
    {
        $user = $request->user();

        $incidentsQuery = Incident::query()
            ->active()
            ->orderByDesc('created_at');

        if ($user->isRegional()) {
            $incidentsQuery->where(function ($q) use ($user) {
                $q->whereHas('cityMunicipalities', function ($cm) use ($user) {
                    $cm->whereHas('province', fn ($pq) => $pq->where('region_id', $user->region_id));
                })->orWhere(function ($q) use ($user) {
                    $q->doesntHave('cityMunicipalities')
                        ->whereHas('creator', fn ($cq) => $cq->where('region_id', $user->region_id));
                });
            });
        } elseif ($user->isProvincial()) {
            $incidentsQuery->where(function ($q) use ($user) {
                $q->whereHas('cityMunicipalities', function ($cm) use ($user) {
                    $cm->where('province_id', $user->province_id);
                })->orWhere(function ($q) use ($user) {
                    $q->doesntHave('cityMunicipalities')
                        ->whereHas('creator', fn ($cq) => $cq->where('region_id', $user->province?->region_id));
                });
            });
        }

        $incidents = $incidentsQuery->get(['id', 'name', 'display_name']);

        if (! $incident && $incidents->isNotEmpty()) {
            $incident = Incident::find($incidents->first()->id);
        }

        $cutoffs = [];
        if ($incident) {
            $provinceId = $user->isProvincial() ? $user->province_id : null;
            $regionId = $user->isRegional() ? $user->region_id : null;
            $validatedOnly = $user->isRegional();

            $result = $this->consolidatedService->consolidateByCutoff($incident, $provinceId, $regionId, $validatedOnly);
            $cutoffs = $result['cutoffs'];
        }

        return Inertia::render('DisplayBoard/Index', [
            'incidents' => $incidents,
            'selectedIncident' => $incident,
            'cutoffs' => $cutoffs,
        ]);
    }
}
