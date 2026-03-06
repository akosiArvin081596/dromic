<?php

use App\Enums\AugmentationType;
use App\Events\DeliveryRecorded;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\RequestLetter;
use App\Models\User;
use App\Services\IncidentNotificationService;
use Illuminate\Support\Facades\Event;

function setupDeliveryBroadcastData(): array
{
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser = User::factory()->lgu($lgu)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($lgu->id);

    $letter = RequestLetter::factory()->create([
        'incident_id' => $incident->id,
        'user_id' => $lguUser->id,
        'city_municipality_id' => $lgu->id,
        'augmentation_items' => [
            ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 100, 'endorsed_quantity' => 80, 'approved_quantity' => 70],
        ],
        'status' => 'approved',
        'endorsed_by' => $provincial->id,
        'endorsed_at' => now(),
        'approved_by' => $regional->id,
        'approved_at' => now(),
    ]);

    return compact('region', 'province', 'lgu', 'admin', 'regional', 'provincial', 'lguUser', 'incident', 'letter');
}

test('DeliveryRecorded event is dispatched when a delivery is recorded', function () {
    Event::fake([DeliveryRecorded::class]);

    $data = setupDeliveryBroadcastData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertRedirect();

    Event::assertDispatched(DeliveryRecorded::class);
});

test('DeliveryRecorded event payload contains request letter data', function () {
    $data = setupDeliveryBroadcastData();

    $serialized = DeliveryRecorded::serializeRequestLetter($data['letter'], $data['regional']);

    expect($serialized)
        ->toMatchArray([
            'id' => $data['letter']->id,
            'incident_id' => $data['incident']->id,
            'incident_name' => $data['incident']->name,
            'city_municipality_name' => $data['lgu']->name,
            'action' => 'delivered',
        ])
        ->toHaveKey('message');
});

test('DeliveryRecorded event broadcasts to correct private channels', function () {
    $data = setupDeliveryBroadcastData();

    $service = new IncidentNotificationService;
    $recipientIds = $service->getDeliveryRecipientUserIds(
        $data['lgu']->id,
        $data['lguUser']->id,
        $data['regional']->id,
    );

    $event = new DeliveryRecorded(
        DeliveryRecorded::serializeRequestLetter($data['letter'], $data['regional']),
        $recipientIds,
    );

    $channels = $event->broadcastOn();

    foreach ($recipientIds as $userId) {
        expect(collect($channels)->pluck('name'))
            ->toContain("private-App.Models.User.{$userId}");
    }
});

test('delivery notification recipients include LGU submitter and admin but exclude recording user', function () {
    $data = setupDeliveryBroadcastData();

    $service = new IncidentNotificationService;
    $recipientIds = $service->getDeliveryRecipientUserIds(
        $data['lgu']->id,
        $data['lguUser']->id,
        $data['regional']->id,
    );

    expect($recipientIds)
        ->toContain($data['admin']->id)
        ->toContain($data['lguUser']->id)
        ->toContain($data['provincial']->id)
        ->not->toContain($data['regional']->id);
});
