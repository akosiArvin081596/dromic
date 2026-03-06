<?php

use App\Events\ReportSubmitted;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;
use App\Services\IncidentNotificationService;
use Illuminate\Support\Facades\Event;

function setupReportBroadcastData(): array
{
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id, 'psgc_code' => '9876543210']);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser = User::factory()->lgu($lgu)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($lgu->id);

    return compact('admin', 'regional', 'provincial', 'lguUser', 'region', 'province', 'lgu', 'incident');
}

function validReportDataForBroadcast(): array
{
    return [
        'report_date' => '2026-01-15',
        'report_time' => '6:00 AM',
        'situation_overview' => 'Test situation overview.',
        'status' => 'for_validation',
        'affected_areas' => [
            ['barangay' => 'Brgy. Test', 'families' => 10, 'persons' => 50],
        ],
        'inside_evacuation_centers' => [],
        'age_distribution' => [
            '0-5' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '6-12' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '13-17' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '18-35' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '36-59' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '60-69' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '70+' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
        ],
        'vulnerable_sectors' => [
            'Pregnant/Lactating' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            'Solo Parent' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            'PWD' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            'Indigenous People' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            'Senior Citizen' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
        ],
        'outside_evacuation_centers' => [],
        'non_idps' => [],
        'damaged_houses' => [],
        'related_incidents' => [],
        'casualties_injured' => [],
        'casualties_missing' => [],
        'casualties_dead' => [],
        'infrastructure_damages' => [],
        'agriculture_damages' => [],
        'assistance_provided' => [],
        'class_suspensions' => [],
        'work_suspensions' => [],
        'lifelines_roads_bridges' => [],
        'lifelines_power' => [],
        'lifelines_water' => [],
        'lifelines_communication' => [],
        'seaport_status' => [],
        'airport_status' => [],
        'landport_status' => [],
        'stranded_passengers' => [],
        'calamity_declarations' => [],
        'preemptive_evacuations' => [],
        'gaps_challenges' => [],
        'response_actions' => null,
    ];
}

test('event is dispatched when LGU submits report for validation on create', function () {
    Event::fake([ReportSubmitted::class]);

    $data = setupReportBroadcastData();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", validReportDataForBroadcast())
        ->assertRedirect();

    Event::assertDispatched(ReportSubmitted::class);
});

test('event is not dispatched when LGU saves report as draft', function () {
    Event::fake([ReportSubmitted::class]);

    $data = setupReportBroadcastData();
    $payload = validReportDataForBroadcast();
    $payload['status'] = 'draft';

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $payload)
        ->assertRedirect();

    Event::assertNotDispatched(ReportSubmitted::class);
});

test('event is dispatched when LGU updates draft report to for_validation', function () {
    Event::fake([ReportSubmitted::class]);

    $data = setupReportBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'draft',
    ]);

    $this->actingAs($data['lguUser'])
        ->put("/incidents/{$data['incident']->id}/reports/{$report->id}", validReportDataForBroadcast())
        ->assertRedirect();

    Event::assertDispatched(ReportSubmitted::class);
});

test('event is not dispatched when report is already for_validation on update', function () {
    Event::fake([ReportSubmitted::class]);

    $data = setupReportBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    // Policy prevents editing non-draft reports, so this returns 403
    $this->actingAs($data['lguUser'])
        ->put("/incidents/{$data['incident']->id}/reports/{$report->id}", validReportDataForBroadcast())
        ->assertForbidden();

    Event::assertNotDispatched(ReportSubmitted::class);
});

test('event broadcasts to correct per-user private channels', function () {
    $data = setupReportBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $service = new IncidentNotificationService;
    $recipientIds = $service->getRecipientUserIds(
        [$data['lgu']->id],
        $data['lguUser']->id,
    );

    $event = new ReportSubmitted(
        ReportSubmitted::serializeReport($report),
        $recipientIds,
    );

    $channels = $event->broadcastOn();

    foreach ($recipientIds as $userId) {
        expect(collect($channels)->pluck('name'))
            ->toContain("private-App.Models.User.{$userId}");
    }
});

test('event payload contains report data', function () {
    $data = setupReportBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'report_number' => 'DROMIC-TEST-001',
        'status' => 'for_validation',
    ]);

    $serialized = ReportSubmitted::serializeReport($report);
    $event = new ReportSubmitted($serialized, [$data['provincial']->id]);

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('report')
        ->and($payload['report'])->toMatchArray([
            'id' => $report->id,
            'report_number' => 'DROMIC-TEST-001',
            'status' => 'for_validation',
            'incident_id' => $data['incident']->id,
            'incident_name' => $data['incident']->fresh()->display_name ?? $data['incident']->name,
            'city_municipality_name' => $data['lgu']->name,
            'user_name' => $data['lguUser']->name,
        ])
        ->and($payload['report'])->toHaveKey('message');
});

test('notification recipients include provincial and admin but exclude regional and submitting LGU user', function () {
    $data = setupReportBroadcastData();

    $service = new IncidentNotificationService;
    $recipientIds = $service->getRecipientUserIds(
        [$data['lgu']->id],
        $data['lguUser']->id,
    );

    expect($recipientIds)
        ->toContain($data['admin']->id)
        ->toContain($data['provincial']->id)
        ->not->toContain($data['regional']->id)
        ->not->toContain($data['lguUser']->id);
});

test('serializeReport generates resubmission message when isResubmission is true', function () {
    $data = setupReportBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $serialized = ReportSubmitted::serializeReport($report, isResubmission: true);

    expect($serialized['message'])
        ->toContain('re-submitted')
        ->toContain('DROMIC report')
        ->toContain($data['incident']->name);
});

test('serializeReport generates standard submission message when isResubmission is false', function () {
    $data = setupReportBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $serialized = ReportSubmitted::serializeReport($report);

    expect($serialized['message'])
        ->toContain('submitted')
        ->not->toContain('re-submitted');
});

test('event is dispatched with resubmission message when LGU resubmits returned report', function () {
    Event::fake([ReportSubmitted::class]);

    $data = setupReportBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'returned',
        'return_reason' => 'Needs corrections',
    ]);

    $this->actingAs($data['lguUser'])
        ->put("/incidents/{$data['incident']->id}/reports/{$report->id}", validReportDataForBroadcast())
        ->assertRedirect();

    Event::assertDispatched(ReportSubmitted::class, function (ReportSubmitted $event) {
        return str_contains($event->reportData['message'], 're-submitted');
    });
});
