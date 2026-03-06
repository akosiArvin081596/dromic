<?php

use App\Enums\AugmentationType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\RequestLetter;
use App\Models\User;

function setupEndorsementData(): array
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
            ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 100],
            ['type' => AugmentationType::HygieneKits->value, 'quantity' => 50],
        ],
        'status' => 'pending',
    ]);

    return compact('region', 'province', 'lgu', 'admin', 'regional', 'provincial', 'lguUser', 'incident', 'letter');
}

test('provincial user can endorse a pending request letter', function () {
    $data = setupEndorsementData();

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/endorse", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'endorsed_quantity' => 80],
                ['type' => AugmentationType::HygieneKits->value, 'endorsed_quantity' => 50],
            ],
        ])
        ->assertRedirect();

    $data['letter']->refresh();
    expect($data['letter']->status->value)->toBe('endorsed');
    expect($data['letter']->endorsed_by)->toBe($data['provincial']->id);
    expect($data['letter']->endorsed_at)->not->toBeNull();
    expect($data['letter']->augmentation_items[0]['endorsed_quantity'])->toBe(80);
    expect($data['letter']->augmentation_items[1]['endorsed_quantity'])->toBe(50);
});

test('provincial user cannot endorse letter from different province', function () {
    $data = setupEndorsementData();
    $otherProvince = Province::factory()->create(['region_id' => $data['region']->id]);
    $otherProvincial = User::factory()->provincial($otherProvince)->create();

    $this->actingAs($otherProvincial)
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/endorse", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'endorsed_quantity' => 80],
                ['type' => AugmentationType::HygieneKits->value, 'endorsed_quantity' => 50],
            ],
        ])
        ->assertForbidden();
});

test('admin cannot endorse request letter', function () {
    $data = setupEndorsementData();

    $this->actingAs($data['admin'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/endorse", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'endorsed_quantity' => 80],
            ],
        ])
        ->assertForbidden();
});

test('regional cannot endorse request letter', function () {
    $data = setupEndorsementData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/endorse", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'endorsed_quantity' => 80],
            ],
        ])
        ->assertForbidden();
});

test('lgu user cannot endorse request letter', function () {
    $data = setupEndorsementData();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/endorse", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'endorsed_quantity' => 80],
            ],
        ])
        ->assertForbidden();
});

test('cannot endorse a non-pending request letter', function () {
    $data = setupEndorsementData();
    $data['letter']->update(['status' => 'endorsed']);

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/endorse", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'endorsed_quantity' => 80],
            ],
        ])
        ->assertForbidden();
});

test('endorsed quantity cannot exceed requested quantity', function () {
    $data = setupEndorsementData();

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/endorse", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'endorsed_quantity' => 200],
                ['type' => AugmentationType::HygieneKits->value, 'endorsed_quantity' => 50],
            ],
        ])
        ->assertSessionHasErrors('augmentation_items.0.endorsed_quantity');
});
