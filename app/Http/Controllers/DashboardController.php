<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isRegionalDirector()) {
            return redirect()->route('rd-dashboard');
        }

        $incidentsQuery = Incident::query()->active()->withCount([
            'reports as my_reports_count' => function ($q) use ($user) {
                if ($user->isLgu()) {
                    $q->where('user_id', $user->id);
                } elseif ($user->isProvincial()) {
                    $q->whereHas('cityMunicipality', fn ($cm) => $cm->where('province_id', $user->province_id));
                } elseif ($user->isRegional()) {
                    $q->where('status', 'validated')
                        ->whereHas('cityMunicipality', fn ($cm) => $cm->whereHas('province', fn ($pq) => $pq->where('region_id', $user->region_id)));
                }
            },
            'cityMunicipalities',
        ]);

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
        } elseif ($user->isLgu()) {
            $incidentsQuery->where(function ($q) use ($user) {
                $q->forCityMunicipality($user->city_municipality_id)
                    ->orWhere(function ($q) use ($user) {
                        $q->doesntHave('cityMunicipalities')
                            ->whereHas('creator', fn ($cq) => $cq->where('region_id', $user->cityMunicipality?->province?->region_id));
                    });
            });
        }

        $activeIncidents = $incidentsQuery->latest()->limit(10)->get();

        $reportCounts = [];
        if ($user->isRegional()) {
            $reportCounts = [
                'draft' => 0,
                'for_validation' => 0,
                'validated' => $this->scopedReportQuery($user)->where('status', 'validated')->count(),
                'returned' => 0,
            ];
        } else {
            foreach (['draft', 'for_validation', 'validated', 'returned'] as $status) {
                $reportCounts[$status] = $this->scopedReportQuery($user)->where('status', $status)->count();
            }
        }

        return Inertia::render('Dashboard', [
            'activeIncidents' => $activeIncidents,
            'reportCounts' => $reportCounts,
            'reportActivity' => Inertia::defer(fn () => $this->getReportActivity($user)),
        ]);
    }

    /** @return Builder<Report> */
    private function scopedReportQuery(User $user): Builder
    {
        $query = Report::query();

        if ($user->isLgu()) {
            $query->where('user_id', $user->id);
        } elseif ($user->isProvincial()) {
            $query->whereHas('cityMunicipality', fn ($q) => $q->where('province_id', $user->province_id));
        } elseif ($user->isRegional()) {
            $query->whereHas('cityMunicipality', fn ($q) => $q->whereHas('province', fn ($pq) => $pq->where('region_id', $user->region_id)));
        }

        return $query;
    }

    /**
     * @return list<array{incident_id: int, incident_name: string, total_lgus: int, reporting_lgus: int, provinces?: list<array{province_name: string, total_lgus: int, reporting_lgus: int}>}>|null
     */
    private function getReportActivity(User $user): ?array
    {
        if ($user->isLgu()) {
            return null;
        }

        $incidentsQuery = Incident::query()->active()->with('cityMunicipalities.province');

        if ($user->isProvincial()) {
            $incidentsQuery->whereHas('cityMunicipalities', fn ($q) => $q->where('province_id', $user->province_id));
        } elseif ($user->isRegional()) {
            $incidentsQuery->whereHas('cityMunicipalities', fn ($q) => $q->whereHas('province', fn ($pq) => $pq->where('region_id', $user->region_id)));
        }

        $incidents = $incidentsQuery->latest()->limit(10)->get();

        return $incidents->map(function (Incident $incident) use ($user) {
            $cityMunicipalities = $incident->cityMunicipalities;

            if ($user->isProvincial()) {
                $cityMunicipalities = $cityMunicipalities->where('province_id', $user->province_id);
            } elseif ($user->isRegional()) {
                $cityMunicipalities = $cityMunicipalities->filter(
                    fn ($cm) => $cm->province?->region_id === $user->region_id
                );
            }

            $lguIds = $cityMunicipalities->pluck('id');
            $totalLgus = $lguIds->count();

            $reportingLgusQuery = Report::where('incident_id', $incident->id)
                ->whereIn('city_municipality_id', $lguIds);

            if ($user->isRegional()) {
                $reportingLgusQuery->where('status', 'validated');
            }

            $reportingLgus = $totalLgus > 0
                ? $reportingLgusQuery->distinct('city_municipality_id')
                    ->count('city_municipality_id')
                : 0;

            $entry = [
                'incident_id' => $incident->id,
                'incident_name' => $incident->display_name ?? $incident->name,
                'total_lgus' => $totalLgus,
                'reporting_lgus' => $reportingLgus,
            ];

            if ($user->isAdmin() || $user->isRegional()) {
                $grouped = $cityMunicipalities->groupBy(fn ($cm) => $cm->province_id);

                $entry['provinces'] = $grouped->map(function ($cms, $provinceId) use ($incident, $user) {
                    $provinceLguIds = $cms->pluck('id');
                    $provinceQuery = Report::where('incident_id', $incident->id)
                        ->whereIn('city_municipality_id', $provinceLguIds);

                    if ($user->isRegional()) {
                        $provinceQuery->where('status', 'validated');
                    }

                    $provinceReportingLgus = $provinceQuery->distinct('city_municipality_id')
                        ->count('city_municipality_id');

                    return [
                        'province_name' => $cms->first()->province?->name ?? 'Unknown',
                        'total_lgus' => $cms->count(),
                        'reporting_lgus' => $provinceReportingLgus,
                    ];
                })->values()->all();
            }

            return $entry;
        })->all();
    }
}
