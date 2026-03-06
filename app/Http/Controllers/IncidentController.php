<?php

namespace App\Http\Controllers;

use App\Enums\AugmentationType;
use App\Enums\UserRole;
use App\Events\IncidentCreated;
use App\Http\Requests\StoreIncidentRequest;
use App\Http\Requests\UpdateIncidentRequest;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Report;
use App\Models\RequestLetter;
use App\Models\User;
use App\Notifications\IncidentCreatedNotification;
use App\Services\IncidentNotificationService;
use App\Services\RequestLetterLedgerService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class IncidentController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Incident::class);

        $user = $request->user();

        $query = Incident::query()
            ->with('cityMunicipalities.province', 'creator')
            ->withCount('reports')
            ->withCount(['reports as reporting_lgus_count' => function ($q) {
                $q->select(\Illuminate\Support\Facades\DB::raw('count(distinct city_municipality_id)'));
            }]);

        $this->applyScopeFilter($query, $user);

        // Summary stats (unaffected by search/status filters)
        $statsQuery = clone $query;
        $incidentCounts = [
            'total' => $statsQuery->count(),
            'active' => (clone $statsQuery)->where('status', 'active')->count(),
            'closed' => (clone $statsQuery)->where('status', 'closed')->count(),
            'total_reports' => (clone $statsQuery)->withCount('reports')->get()->sum('reports_count'),
        ];

        $query->when($request->input('search'), function ($q, $search) {
            $q->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        });

        $query->when($request->input('status'), function ($q, $status) {
            $q->where('status', $status);
        });

        $incidents = $query->latest()->paginate(15)->withQueryString();

        $returnedReports = [];
        if ($user->isLgu()) {
            $returnedReports = Report::query()
                ->with('incident:id,name,display_name')
                ->where('user_id', $user->id)
                ->where('status', 'returned')
                ->latest('updated_at')
                ->get(['id', 'incident_id', 'report_number', 'report_type', 'sequence_number', 'return_reason']);
        }

        return Inertia::render('Incidents/Index', [
            'incidents' => $incidents,
            'filters' => $request->only(['search', 'status']),
            'incidentCounts' => $incidentCounts,
            'canCreate' => $user->can('create', Incident::class),
            'returnedReports' => $returnedReports,
        ]);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Incident> $query */
    private function applyScopeFilter(Builder $query, User $user): void
    {
        if ($user->isRegional()) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('cityMunicipalities', function ($cm) use ($user) {
                    $cm->whereHas('province', fn ($pq) => $pq->where('region_id', $user->region_id));
                })->orWhere(function ($q) use ($user) {
                    $q->doesntHave('cityMunicipalities')
                        ->whereHas('creator', fn ($cq) => $cq->where('region_id', $user->region_id));
                });
            });
        } elseif ($user->isProvincial()) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('cityMunicipalities', function ($cm) use ($user) {
                    $cm->where('province_id', $user->province_id);
                })->orWhere(function ($q) use ($user) {
                    $q->doesntHave('cityMunicipalities')
                        ->whereHas('creator', fn ($cq) => $cq->where('region_id', $user->province?->region_id));
                });
            });
        } elseif ($user->isLgu()) {
            $query->where(function ($q) use ($user) {
                $q->forCityMunicipality($user->city_municipality_id)
                    ->orWhere(function ($q) use ($user) {
                        $q->doesntHave('cityMunicipalities')
                            ->whereHas('creator', fn ($cq) => $cq->where('region_id', $user->cityMunicipality?->province?->region_id));
                    });
            });
        }
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Incident::class);

        $user = $request->user();

        $provincesQuery = Province::query()->with('cityMunicipalities')->orderBy('name');

        if ($user->isRegional()) {
            $provincesQuery->where('region_id', $user->region_id);
        }

        return Inertia::render('Incidents/Create', [
            'provinces' => $provincesQuery->get(),
            'userRole' => $user->role->value,
            'userCityMunicipalityId' => $user->city_municipality_id,
        ]);
    }

    public function store(StoreIncidentRequest $request): RedirectResponse
    {
        $this->authorize('create', Incident::class);

        $user = $request->user();
        $data = $request->validated();

        $incident = Incident::query()->create([
            'name' => $data['name'],
            'display_name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'created_by' => $user->id,
        ]);

        // For LGU users creating local incidents, auto-assign their LGU
        if ($user->isLgu()) {
            $cityMunicipalityIds = [$user->city_municipality_id];
        } else {
            $cityMunicipalityIds = $data['city_municipality_ids'] ?? [];
        }

        if (count($cityMunicipalityIds) > 0) {
            $incident->cityMunicipalities()->attach($cityMunicipalityIds);
        }

        $notificationService = app(IncidentNotificationService::class);

        // For massive incidents with no LGUs, notify all users in the creator's region
        if (count($cityMunicipalityIds) === 0 && $user->region_id) {
            $recipientUserIds = $notificationService->getRegionRecipientUserIds($user->region_id, $user->id);
        } else {
            $recipientUserIds = $notificationService->getRecipientUserIds($cityMunicipalityIds, $user->id);
        }

        if (count($recipientUserIds) > 0) {
            $incidentData = IncidentCreated::serializeIncident($incident, $user);

            IncidentCreated::dispatch($incidentData, $recipientUserIds);

            $recipients = User::query()->whereIn('id', $recipientUserIds)->get();
            Notification::send($recipients, new IncidentCreatedNotification($incidentData));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Incident created successfully.']);

        return redirect()->route('incidents.show', $incident);
    }

    public function show(Request $request, Incident $incident): Response
    {
        $this->authorize('view', $incident);

        $incident->load('cityMunicipalities.province', 'creator');

        $user = $request->user();

        $reportsQuery = $incident->reports()
            ->with('cityMunicipality.province', 'user')
            ->orderBy('city_municipality_id')
            ->orderBy('sequence_number');

        if ($user->isRegional()) {
            $reportsQuery->where('status', 'validated')
                ->whereHas('cityMunicipality', function ($q) use ($user) {
                    $q->whereHas('province', fn ($pq) => $pq->where('region_id', $user->region_id));
                });
        } elseif ($user->isProvincial()) {
            $reportsQuery->whereHas('cityMunicipality', function ($q) use ($user) {
                $q->where('province_id', $user->province_id);
            });
        } elseif ($user->isLgu()) {
            $reportsQuery->where('city_municipality_id', $user->city_municipality_id);
        }

        $reports = $reportsQuery->get();

        // Group reports by LGU for timeline view
        $reportsByLgu = $reports->groupBy('city_municipality_id');

        // Request letters with geo-scoping
        $requestLettersQuery = $incident->requestLetters()
            ->with('cityMunicipality.province', 'user', 'endorser', 'approver', 'deliveries.attachments', 'deliveries.escort', 'deliveryPlan.creator')
            ->orderBy('city_municipality_id')
            ->latest();

        if ($user->isRegional()) {
            $requestLettersQuery->whereHas('cityMunicipality', function ($q) use ($user) {
                $q->whereHas('province', fn ($pq) => $pq->where('region_id', $user->region_id));
            })->whereNot('status', \App\Enums\RequestLetterStatus::Pending);
        } elseif ($user->isProvincial()) {
            $requestLettersQuery->whereHas('cityMunicipality', function ($q) use ($user) {
                $q->where('province_id', $user->province_id);
            });
        } elseif ($user->isLgu()) {
            $requestLettersQuery->where('city_municipality_id', $user->city_municipality_id);
        }

        $requestLetters = $requestLettersQuery->get();

        $ledgerService = app(RequestLetterLedgerService::class);

        $requestLetters->each(function (RequestLetter $letter) use ($user, $ledgerService) {
            $letter->setAttribute('ledger', $ledgerService->getLedger($letter));
            $letter->setAttribute('can_endorse', $user->can('endorse', $letter));
            $letter->setAttribute('can_approve', $user->can('approve', $letter));
            $letter->setAttribute('can_record_delivery', $user->can('recordDelivery', $letter));
            $letter->setAttribute('can_delete', $user->can('delete', $letter));
        });

        $isAssignedLgu = $user->isLgu() && (
            $incident->cityMunicipalities()->where('city_municipalities.id', $user->city_municipality_id)->exists()
            || (! $incident->cityMunicipalities()->exists() && $user->cityMunicipality?->province?->region_id === $incident->creator?->region_id)
        );
        $canUploadRequestLetter = $isAssignedLgu && $incident->reports()->where('city_municipality_id', $user->city_municipality_id)->exists();

        $augmentationTypes = collect(AugmentationType::cases())->map(fn (AugmentationType $type) => [
            'value' => $type->value,
            'label' => $type->label(),
        ])->all();

        $escortUsers = User::query()
            ->where('role', UserRole::Escort)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Incidents/Show', [
            'incident' => $incident,
            'reportsByLgu' => $reportsByLgu,
            'canCreateReport' => $isAssignedLgu,
            'requestLettersByLgu' => $requestLetters->groupBy('city_municipality_id'),
            'canUploadRequestLetter' => $canUploadRequestLetter,
            'augmentationTypes' => $augmentationTypes,
            'escortUsers' => $escortUsers,
        ]);
    }

    public function edit(Request $request, Incident $incident): Response
    {
        $this->authorize('update', $incident);

        $user = $request->user();
        $incident->load('cityMunicipalities');

        $provincesQuery = Province::query()->with('cityMunicipalities')->orderBy('name');

        if ($user->isRegional()) {
            $provincesQuery->where('region_id', $user->region_id);
        }

        return Inertia::render('Incidents/Edit', [
            'incident' => $incident,
            'provinces' => $provincesQuery->get(),
        ]);
    }

    public function update(UpdateIncidentRequest $request, Incident $incident): RedirectResponse
    {
        $this->authorize('update', $incident);

        $data = $request->validated();

        $nameChanged = $incident->name !== $data['name'];

        $incident->update([
            'name' => $data['name'],
            'type' => $data['type'],
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? $incident->status,
        ]);

        $incident->cityMunicipalities()->sync($data['city_municipality_ids'] ?? []);

        if ($nameChanged) {
            $incident->refreshDisplayName();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Incident updated successfully.']);

        return redirect()->route('incidents.show', $incident);
    }
}
