<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Models\Incident;
use App\Services\RdDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RdDashboardController extends Controller
{
    public function __construct(private RdDashboardService $dashboardService) {}

    public function index(Request $request, ?Incident $incident = null): Response
    {
        $user = $request->user();
        $regionId = $user->region_id;

        $incidents = Incident::query()
            ->active()
            ->where(function ($q) use ($regionId) {
                $q->whereHas('cityMunicipalities', function ($cm) use ($regionId) {
                    $cm->whereHas('province', fn ($pq) => $pq->where('region_id', $regionId));
                })->orWhere(function ($q) use ($regionId) {
                    $q->doesntHave('cityMunicipalities')
                        ->whereHas('creator', fn ($cq) => $cq->where('region_id', $regionId));
                });
            })
            ->latest()
            ->get(['id', 'name', 'type', 'status', 'created_by']);

        // Auto-select the latest massive incident when none is specified
        if (! $incident) {
            $incident = $incidents->firstWhere('type', IncidentType::Massive);
        }

        $dashboardData = null;

        if ($incident) {
            // Verify the incident belongs to this RD's region
            $belongsToRegion = $incident->cityMunicipalities()
                ->whereHas('province', fn ($q) => $q->where('region_id', $regionId))
                ->exists();

            if (! $belongsToRegion) {
                // Also check massive incidents
                $isMassiveInRegion = ! $incident->cityMunicipalities()->exists()
                    && $incident->creator?->region_id === $regionId;

                if (! $isMassiveInRegion) {
                    abort(403);
                }
            }

            $dashboardData = [
                'impact' => $this->dashboardService->getImpactSummary($incident, $regionId),
                'augmentation' => $this->dashboardService->getAugmentationSummary($incident, $regionId),
            ];
        }

        return Inertia::render('RdDashboard', [
            'incidents' => $incidents,
            'selectedIncidentId' => $incident?->id,
            'dashboardData' => $dashboardData,
        ]);
    }
}
