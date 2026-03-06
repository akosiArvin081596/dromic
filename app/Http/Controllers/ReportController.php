<?php

namespace App\Http\Controllers;

use App\Enums\ReportType;
use App\Events\ReportReturned;
use App\Events\ReportSubmitted;
use App\Events\ReportValidated;
use App\Http\Requests\ReturnReportRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\Barangay;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Report;
use App\Models\User;
use App\Notifications\ReportReturnedNotification;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\ReportValidatedNotification;
use App\Services\IncidentNotificationService;
use App\Services\ReportSequenceService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private ReportSequenceService $sequenceService) {}

    /**
     * Compute the next cut-off period based on server time.
     *
     * @return array{report_date: string, report_time: string}
     */
    private function computeCutoff(?Carbon $now = null): array
    {
        $now ??= now();

        if ($now->hour < 12) {
            return [
                'report_date' => $now->toDateString(),
                'report_time' => '12:00 PM',
            ];
        }

        return [
            'report_date' => $now->copy()->addDay()->toDateString(),
            'report_time' => '12:00 AM',
        ];
    }

    private function broadcastIfSubmitted(Report $report, int $excludeUserId, bool $isResubmission = false): void
    {
        if ($report->status !== 'for_validation') {
            return;
        }

        $notificationService = app(IncidentNotificationService::class);
        $recipientUserIds = $notificationService->getRecipientUserIds(
            [$report->city_municipality_id],
            $excludeUserId,
        );

        if (count($recipientUserIds) > 0) {
            $reportData = ReportSubmitted::serializeReport($report, isResubmission: $isResubmission);

            ReportSubmitted::dispatch($reportData, $recipientUserIds);

            $recipients = User::query()->whereIn('id', $recipientUserIds)->get();
            Notification::send($recipients, new ReportSubmittedNotification($reportData));
        }
    }

    public function index(Request $request, Incident $incident): Response
    {
        $user = $request->user();

        $query = $incident->reports()
            ->with('cityMunicipality.province', 'user')
            ->orderByDesc('sequence_number');

        if ($user->isRegional()) {
            $query->where('status', 'validated')
                ->whereHas('cityMunicipality', function ($q) use ($user) {
                    $q->whereHas('province', fn ($pq) => $pq->where('region_id', $user->region_id));
                });
        } elseif ($user->isProvincial()) {
            $query->whereHas('cityMunicipality', function ($q) use ($user) {
                $q->where('province_id', $user->province_id);
            });
        } elseif ($user->isLgu()) {
            $query->where('city_municipality_id', $user->city_municipality_id);
        }

        $query->when($request->input('status'), function ($q, $status) {
            $q->byStatus($status);
        });

        $reports = $query->paginate(15)->withQueryString();

        return Inertia::render('Reports/Index', [
            'incident' => $incident,
            'reports' => $reports,
            'filters' => $request->only(['status']),
        ]);
    }

    public function create(Request $request, Incident $incident): Response
    {
        $this->authorize('create', [Report::class, $incident]);

        $user = $request->user();
        $nextInfo = $this->sequenceService->getNextReportInfo($incident->id, $user->city_municipality_id);

        $prefillData = null;
        if ($nextInfo['previous_report_id']) {
            $previousReport = Report::find($nextInfo['previous_report_id']);
            if ($previousReport) {
                $prefillData = $this->sequenceService->copyFromPrevious($previousReport);
            }
        }

        $lgu = $user->cityMunicipality;
        $reportNumber = $this->sequenceService->generateReportNumber(
            $incident,
            $lgu,
            $nextInfo['type'],
            $nextInfo['sequence'],
        );

        $nextCutoff = $this->computeCutoff();

        return Inertia::render('Reports/Create', [
            'incident' => $incident->load('cityMunicipalities.province'),
            'reportNumber' => $reportNumber,
            'reportType' => $nextInfo['type']->value,
            'sequenceNumber' => $nextInfo['sequence'],
            'previousReportId' => $nextInfo['previous_report_id'],
            'prefillData' => $prefillData,
            'nextCutoff' => $nextCutoff,
            'provinces' => Province::query()->with('cityMunicipalities')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreReportRequest $request, Incident $incident): RedirectResponse
    {
        $this->authorize('create', [Report::class, $incident]);

        $user = $request->user();
        $validated = $request->validated();
        $cutoff = $this->computeCutoff();

        $report = DB::transaction(function () use ($validated, $incident, $user, $cutoff) {
            $nextInfo = $this->sequenceService->getNextReportInfo($incident->id, $user->city_municipality_id);

            $isTerminal = $validated['is_terminal'] ?? false;
            $reportType = $isTerminal ? ReportType::Terminal : $nextInfo['type'];
            $sequence = $isTerminal && $nextInfo['type'] === ReportType::Progress
                ? $nextInfo['sequence']
                : $nextInfo['sequence'];

            $lgu = $user->cityMunicipality;
            $reportNumber = $this->sequenceService->generateReportNumber(
                $incident,
                $lgu,
                $reportType,
                $sequence,
            );

            return Report::query()->create([
                'user_id' => $user->id,
                'incident_id' => $incident->id,
                'report_number' => $reportNumber,
                'report_type' => $reportType,
                'sequence_number' => $sequence,
                'previous_report_id' => $nextInfo['previous_report_id'],
                'city_municipality_id' => $user->city_municipality_id,
                'report_date' => $cutoff['report_date'],
                'report_time' => $cutoff['report_time'],
                'situation_overview' => $validated['situation_overview'],
                'affected_areas' => $validated['affected_areas'],
                'inside_evacuation_centers' => $validated['inside_evacuation_centers'],
                'age_distribution' => $validated['age_distribution'],
                'vulnerable_sectors' => $validated['vulnerable_sectors'],
                'outside_evacuation_centers' => $validated['outside_evacuation_centers'],
                'non_idps' => $validated['non_idps'],
                'damaged_houses' => $validated['damaged_houses'],
                'related_incidents' => $validated['related_incidents'],
                'casualties_injured' => $validated['casualties_injured'],
                'casualties_missing' => $validated['casualties_missing'],
                'casualties_dead' => $validated['casualties_dead'],
                'infrastructure_damages' => $validated['infrastructure_damages'],
                'agriculture_damages' => $validated['agriculture_damages'],
                'assistance_provided' => $validated['assistance_provided'],
                'class_suspensions' => $validated['class_suspensions'],
                'work_suspensions' => $validated['work_suspensions'],
                'lifelines_roads_bridges' => $validated['lifelines_roads_bridges'],
                'lifelines_power' => $validated['lifelines_power'],
                'lifelines_water' => $validated['lifelines_water'],
                'lifelines_communication' => $validated['lifelines_communication'],
                'seaport_status' => $validated['seaport_status'],
                'airport_status' => $validated['airport_status'],
                'landport_status' => $validated['landport_status'],
                'stranded_passengers' => $validated['stranded_passengers'],
                'calamity_declarations' => $validated['calamity_declarations'],
                'preemptive_evacuations' => $validated['preemptive_evacuations'],
                'gaps_challenges' => $validated['gaps_challenges'],
                'response_actions' => $validated['response_actions'] ?? null,
                'status' => $validated['status'] ?? 'draft',
            ]);
        });

        $this->broadcastIfSubmitted($report, $user->id);

        return redirect()->route('incidents.reports.show', [$incident, $report])->with('success', 'Report created successfully.');
    }

    public function show(Incident $incident, Report $report): Response
    {
        $this->authorize('view', $report);

        $report->load('cityMunicipality.province', 'user', 'incident');

        return Inertia::render('Reports/Show', [
            'incident' => $incident,
            'report' => $report,
        ]);
    }

    public function edit(Request $request, Incident $incident, Report $report): Response
    {
        $this->authorize('update', $report);

        $report->load('cityMunicipality.province');

        $nextCutoff = $this->computeCutoff();

        return Inertia::render('Reports/Edit', [
            'incident' => $incident,
            'report' => $report,
            'nextCutoff' => $nextCutoff,
            'provinces' => Province::query()->with('cityMunicipalities')->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateReportRequest $request, Incident $incident, Report $report): RedirectResponse
    {
        $this->authorize('update', $report);

        $validated = $request->validated();
        $cutoff = $this->computeCutoff();
        $previousStatus = $report->status;

        $report->update([
            'report_date' => $cutoff['report_date'],
            'report_time' => $cutoff['report_time'],
            'situation_overview' => $validated['situation_overview'],
            'affected_areas' => $validated['affected_areas'],
            'inside_evacuation_centers' => $validated['inside_evacuation_centers'],
            'age_distribution' => $validated['age_distribution'],
            'vulnerable_sectors' => $validated['vulnerable_sectors'],
            'outside_evacuation_centers' => $validated['outside_evacuation_centers'],
            'non_idps' => $validated['non_idps'],
            'damaged_houses' => $validated['damaged_houses'],
            'related_incidents' => $validated['related_incidents'],
            'casualties_injured' => $validated['casualties_injured'],
            'casualties_missing' => $validated['casualties_missing'],
            'casualties_dead' => $validated['casualties_dead'],
            'infrastructure_damages' => $validated['infrastructure_damages'],
            'agriculture_damages' => $validated['agriculture_damages'],
            'assistance_provided' => $validated['assistance_provided'],
            'class_suspensions' => $validated['class_suspensions'],
            'work_suspensions' => $validated['work_suspensions'],
            'lifelines_roads_bridges' => $validated['lifelines_roads_bridges'],
            'lifelines_power' => $validated['lifelines_power'],
            'lifelines_water' => $validated['lifelines_water'],
            'lifelines_communication' => $validated['lifelines_communication'],
            'seaport_status' => $validated['seaport_status'],
            'airport_status' => $validated['airport_status'],
            'landport_status' => $validated['landport_status'],
            'stranded_passengers' => $validated['stranded_passengers'],
            'calamity_declarations' => $validated['calamity_declarations'],
            'preemptive_evacuations' => $validated['preemptive_evacuations'],
            'gaps_challenges' => $validated['gaps_challenges'],
            'response_actions' => $validated['response_actions'] ?? null,
            'status' => $validated['status'] ?? $report->status,
            'return_reason' => ($validated['status'] ?? $report->status) === 'for_validation' ? null : $report->return_reason,
        ]);

        if ($previousStatus !== 'for_validation') {
            $this->broadcastIfSubmitted($report, $request->user()->id, $previousStatus === 'returned');
        }

        return redirect()->route('incidents.reports.show', [$incident, $report])->with('success', 'Report updated successfully.');
    }

    public function validateReport(Request $request, Incident $incident, Report $report): RedirectResponse
    {
        $this->authorize('validate', $report);

        $report->update(['status' => 'validated']);

        $notificationService = app(IncidentNotificationService::class);
        $recipientUserIds = $notificationService->getValidationRecipientUserIds(
            $report->city_municipality_id,
            $report->user_id,
            $request->user()->id,
        );

        if (count($recipientUserIds) > 0) {
            $reportData = ReportValidated::serializeReport($report, $request->user());

            ReportValidated::dispatch($reportData, $recipientUserIds);

            $recipients = User::query()->whereIn('id', $recipientUserIds)->get();
            Notification::send($recipients, new ReportValidatedNotification($reportData));
        }

        return redirect()->route('incidents.reports.show', [$incident, $report])->with('success', 'Report validated successfully.');
    }

    public function returnReport(ReturnReportRequest $request, Incident $incident, Report $report): RedirectResponse
    {
        $this->authorize('returnReport', $report);

        $report->update([
            'status' => 'returned',
            'return_reason' => $request->validated('return_reason'),
        ]);

        $lguCreatorId = $report->user_id;
        if ($lguCreatorId !== $request->user()->id) {
            $reportData = ReportReturned::serializeReport($report, $request->user());

            ReportReturned::dispatch($reportData, [$lguCreatorId]);

            $lguCreator = User::query()->find($lguCreatorId);
            if ($lguCreator) {
                $lguCreator->notify(new ReportReturnedNotification($reportData));
            }
        }

        return redirect()->route('incidents.reports.show', [$incident, $report])->with('success', 'Report returned to LGU.');
    }

    public function destroy(Incident $incident, Report $report): RedirectResponse
    {
        $this->authorize('delete', $report);

        $report->delete();

        return redirect()->route('incidents.reports.index', $incident)->with('success', 'Report deleted successfully.');
    }

    public function barangays(Request $request): JsonResponse
    {
        $request->validate([
            'city_municipality_id' => ['required', 'exists:city_municipalities,id'],
        ]);

        $barangays = Barangay::query()
            ->where('city_municipality_id', $request->input('city_municipality_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'psgc_code']);

        return response()->json($barangays);
    }
}
