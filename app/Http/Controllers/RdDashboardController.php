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

        if (! $user->isRegionalDirector() && ! $user->isViewOnly()) {
            abort(403);
        }

        $regionId = $this->resolveRegionId($user);

        $incidentsQuery = Incident::query()
            ->active()
            ->where(function ($q) use ($regionId) {
                $q->whereHas('cityMunicipalities', function ($cm) use ($regionId) {
                    $cm->whereHas('province', fn ($pq) => $pq->where('region_id', $regionId));
                })->orWhere(function ($q) use ($regionId) {
                    $q->doesntHave('cityMunicipalities')
                        ->whereHas('creator', fn ($cq) => $cq->where('region_id', $regionId));
                });
            });

        if ($user->isLgu()) {
            $incidentsQuery->where(function ($q) use ($user) {
                $q->forCityMunicipality($user->city_municipality_id)
                    ->orWhere(function ($q) use ($user) {
                        $q->doesntHave('cityMunicipalities')
                            ->whereHas('creator', fn ($cq) => $cq->where('region_id', $this->resolveRegionId($user)));
                    });
            });
        } elseif ($user->isProvincial()) {
            $incidentsQuery->whereHas('cityMunicipalities', fn ($q) => $q->where('province_id', $user->province_id));
        }

        $incidents = $incidentsQuery->latest()
            ->get(['id', 'name', 'display_name', 'type', 'status', 'created_by']);

        if (! $incident) {
            $incident = $incidents->firstWhere('type', IncidentType::Massive);
        }

        $dashboardData = null;

        if ($incident) {
            $this->verifyIncidentAccess($user, $incident, $regionId);

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

    private function resolveRegionId($user): int
    {
        if ($user->region_id) {
            return $user->region_id;
        }

        if ($user->province_id) {
            return $user->province->region_id;
        }

        return $user->cityMunicipality->province->region_id;
    }

    private function verifyIncidentAccess($user, Incident $incident, int $regionId): void
    {
        $belongsToRegion = $incident->cityMunicipalities()
            ->whereHas('province', fn ($q) => $q->where('region_id', $regionId))
            ->exists();

        if (! $belongsToRegion) {
            $isMassiveInRegion = ! $incident->cityMunicipalities()->exists()
                && $incident->creator?->region_id === $regionId;

            if (! $isMassiveInRegion) {
                abort(403);
            }
        }

        if ($user->isLgu()) {
            $hasAccess = $incident->cityMunicipalities()
                ->where('city_municipalities.id', $user->city_municipality_id)
                ->exists();

            if (! $hasAccess && $incident->cityMunicipalities()->exists()) {
                abort(403);
            }
        } elseif ($user->isProvincial()) {
            $hasAccess = $incident->cityMunicipalities()
                ->where('province_id', $user->province_id)
                ->exists();

            if (! $hasAccess && $incident->cityMunicipalities()->exists()) {
                abort(403);
            }
        }
    }
}
