<?php

use App\Events\IncidentCreated;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;
use App\Services\IncidentNotificationService;
use Illuminate\Support\Facades\Event;

function setupBroadcastData(): array
{
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu1 = CityMunicipality::factory()->create(['province_id' => $province->id]);
    $lgu2 = CityMunicipality::factory()->create(['province_id' => $province->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser1 = User::factory()->lgu($lgu1)->create();
    $lguUser2 = User::factory()->lgu($lgu2)->create();

    return compact('admin', 'regional', 'provincial', 'lguUser1', 'lguUser2', 'region', 'province', 'lgu1', 'lgu2');
}

test('event is dispatched when admin creates an incident', function () {
    Event::fake([IncidentCreated::class]);

    $data = setupBroadcastData();

    $this->actingAs($data['admin'])
        ->post('/incidents', [
            'category' => 'flood',
            'type' => 'massive',
            'description' => 'A test incident.',
            'city_municipality_ids' => [$data['lgu1']->id],
        ])
        ->assertRedirect();

    Event::assertDispatched(IncidentCreated::class);
});

test('event broadcasts to correct per-user private channels', function () {
    $data = setupBroadcastData();

    $incident = Incident::factory()->create(['created_by' => $data['admin']->id]);
    $incident->cityMunicipalities()->attach([$data['lgu1']->id, $data['lgu2']->id]);

    $service = new IncidentNotificationService;
    $recipientIds = $service->getRecipientUserIds(
        [$data['lgu1']->id, $data['lgu2']->id],
        $data['admin']->id,
    );

    $event = new IncidentCreated(
        IncidentCreated::serializeIncident($incident, $data['admin']),
        $recipientIds,
    );

    $channels = $event->broadcastOn();

    foreach ($recipientIds as $userId) {
        expect(collect($channels)->pluck('name'))
            ->toContain("private-App.Models.User.{$userId}");
    }
});

test('event payload contains incident data', function () {
    $data = setupBroadcastData();

    $incident = Incident::factory()->create([
        'name' => 'Typhoon Payload Test',
        'created_by' => $data['admin']->id,
    ]);

    $serialized = IncidentCreated::serializeIncident($incident, $data['admin']);
    $event = new IncidentCreated($serialized, [$data['lguUser1']->id]);

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKey('incident')
        ->and($payload['incident'])->toMatchArray([
            'id' => $incident->id,
            'name' => 'Typhoon Payload Test',
            'reports_count' => 0,
        ]);
});

test('notification service returns correct scoped user ids', function () {
    $data = setupBroadcastData();

    // Create an unrelated region/province/lgu/user that should NOT be included
    $otherRegion = Region::factory()->create();
    $otherProvince = Province::factory()->create(['region_id' => $otherRegion->id]);
    $otherLgu = CityMunicipality::factory()->create(['province_id' => $otherProvince->id]);
    $otherLguUser = User::factory()->lgu($otherLgu)->create();
    $otherRegionalUser = User::factory()->regional($otherRegion)->create();

    $service = new IncidentNotificationService;
    $recipientIds = $service->getRecipientUserIds(
        [$data['lgu1']->id],
        $data['admin']->id,
    );

    // Should include: provincial (same province), lguUser1 (same LGU)
    expect($recipientIds)
        ->toContain($data['provincial']->id)
        ->toContain($data['lguUser1']->id);

    // Should NOT include: admin (excluded as creator), regional (only notified on validation),
    // lguUser2 (different LGU), other region users
    expect($recipientIds)
        ->not->toContain($data['admin']->id)
        ->not->toContain($data['regional']->id)
        ->not->toContain($data['lguUser2']->id)
        ->not->toContain($otherLguUser->id)
        ->not->toContain($otherRegionalUser->id);
});

test('notification service excludes the creating user', function () {
    $data = setupBroadcastData();

    $service = new IncidentNotificationService;

    // Exclude the lguUser1 who is the "creator"
    $recipientIds = $service->getRecipientUserIds(
        [$data['lgu1']->id],
        $data['lguUser1']->id,
    );

    expect($recipientIds)->not->toContain($data['lguUser1']->id);

    // Admin should be included since they are not the creator
    expect($recipientIds)->toContain($data['admin']->id);
});
