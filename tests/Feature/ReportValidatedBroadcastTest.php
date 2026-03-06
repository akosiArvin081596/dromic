<?php

use App\Events\ReportValidated;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;
use App\Services\IncidentNotificationService;
use Illuminate\Support\Facades\Event;

function setupValidatedBroadcastData(): array
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

test('ReportValidated event is dispatched when provincial validates a report', function () {
    Event::fake([ReportValidated::class]);

    $data = setupValidatedBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/validate")
        ->assertRedirect();

    Event::assertDispatched(ReportValidated::class);
});

test('ReportValidated recipients include LGU creator, admin, and regional but not provincial', function () {
    $data = setupValidatedBroadcastData();

    $service = new IncidentNotificationService;
    $recipientIds = $service->getValidationRecipientUserIds(
        $data['lgu']->id,
        $data['lguUser']->id,
        $data['provincial']->id,
    );

    expect($recipientIds)
        ->toContain($data['lguUser']->id)
        ->toContain($data['admin']->id)
        ->toContain($data['regional']->id)
        ->not->toContain($data['provincial']->id);
});

test('ReportValidated event broadcasts to correct per-user private channels', function () {
    $data = setupValidatedBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'validated',
    ]);

    $service = new IncidentNotificationService;
    $recipientIds = $service->getValidationRecipientUserIds(
        $data['lgu']->id,
        $data['lguUser']->id,
        $data['provincial']->id,
    );

    $event = new ReportValidated(
        ReportValidated::serializeReport($report, $data['provincial']),
        $recipientIds,
    );

    $channels = $event->broadcastOn();

    foreach ($recipientIds as $userId) {
        expect(collect($channels)->pluck('name'))
            ->toContain("private-App.Models.User.{$userId}");
    }
});

test('ReportValidated payload contains correct report data with validated status', function () {
    $data = setupValidatedBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'report_number' => 'DROMIC-TEST-VALIDATED',
        'status' => 'validated',
    ]);

    $serialized = ReportValidated::serializeReport($report, $data['provincial']);
    $event = new ReportValidated($serialized, [$data['lguUser']->id]);

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('report')
        ->and($payload['report'])->toMatchArray([
            'id' => $report->id,
            'report_number' => 'DROMIC-TEST-VALIDATED',
            'status' => 'validated',
            'incident_id' => $data['incident']->id,
            'incident_name' => $data['incident']->name,
            'city_municipality_name' => $data['lgu']->name,
            'user_name' => $data['lguUser']->name,
        ])
        ->and($payload['report'])->toHaveKey('message')
        ->and($payload['report']['message'])->toContain('validated a DROMIC report for');
});
