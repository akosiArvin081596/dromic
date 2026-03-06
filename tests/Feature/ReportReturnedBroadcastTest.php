<?php

use App\Events\ReportReturned;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Event;

function setupReturnedBroadcastData(): array
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

test('ReportReturned event is dispatched when provincial returns a report', function () {
    Event::fake([ReportReturned::class]);

    $data = setupReturnedBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Incomplete data sections.',
        ])
        ->assertRedirect();

    Event::assertDispatched(ReportReturned::class);
});

test('ReportReturned event is dispatched only to LGU creator', function () {
    Event::fake([ReportReturned::class]);

    $data = setupReturnedBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Incomplete data sections.',
        ])
        ->assertRedirect();

    Event::assertDispatched(ReportReturned::class, function (ReportReturned $event) use ($data) {
        return $event->recipientUserIds === [$data['lguUser']->id];
    });
});

test('ReportReturned payload includes return_reason', function () {
    $data = setupReturnedBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'report_number' => 'DROMIC-TEST-RETURNED',
        'status' => 'returned',
        'return_reason' => 'Missing evacuation center data.',
    ]);

    $serialized = ReportReturned::serializeReport($report, $data['provincial']);
    $event = new ReportReturned($serialized, [$data['lguUser']->id]);

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('report')
        ->and($payload['report'])->toMatchArray([
            'id' => $report->id,
            'report_number' => 'DROMIC-TEST-RETURNED',
            'status' => 'returned',
            'return_reason' => 'Missing evacuation center data.',
            'incident_id' => $data['incident']->id,
            'incident_name' => $data['incident']->name,
            'city_municipality_name' => $data['lgu']->name,
            'user_name' => $data['lguUser']->name,
        ])
        ->and($payload['report'])->toHaveKey('message')
        ->and($payload['report']['message'])->toContain('returned a DROMIC report for');
});

test('ReportReturned broadcasts to correct private channel', function () {
    $data = setupReturnedBroadcastData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'returned',
        'return_reason' => 'Needs corrections.',
    ]);

    $event = new ReportReturned(
        ReportReturned::serializeReport($report, $data['provincial']),
        [$data['lguUser']->id],
    );

    $channels = $event->broadcastOn();

    expect($channels)->toHaveCount(1)
        ->and($channels[0]->name)->toBe("private-App.Models.User.{$data['lguUser']->id}");
});
