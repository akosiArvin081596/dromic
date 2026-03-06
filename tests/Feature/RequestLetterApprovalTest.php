<?php

use App\Enums\AugmentationType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\RequestLetter;
use App\Models\User;

function setupApprovalData(): array
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
            ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 100, 'endorsed_quantity' => 80],
            ['type' => AugmentationType::HygieneKits->value, 'quantity' => 50, 'endorsed_quantity' => 50],
        ],
        'status' => 'endorsed',
        'endorsed_by' => $provincial->id,
        'endorsed_at' => now(),
    ]);

    return compact('region', 'province', 'lgu', 'admin', 'regional', 'provincial', 'lguUser', 'incident', 'letter');
}

test('regional user can approve an endorsed request letter', function () {
    $data = setupApprovalData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/approve", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'approved_quantity' => 70],
                ['type' => AugmentationType::HygieneKits->value, 'approved_quantity' => 40],
            ],
        ])
        ->assertRedirect();

    $data['letter']->refresh();
    expect($data['letter']->status->value)->toBe('approved');
    expect($data['letter']->approved_by)->toBe($data['regional']->id);
    expect($data['letter']->approved_at)->not->toBeNull();
    expect($data['letter']->augmentation_items[0]['approved_quantity'])->toBe(70);
    expect($data['letter']->augmentation_items[1]['approved_quantity'])->toBe(40);
});

test('admin can approve an endorsed request letter', function () {
    $data = setupApprovalData();

    $this->actingAs($data['admin'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/approve", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'approved_quantity' => 80],
                ['type' => AugmentationType::HygieneKits->value, 'approved_quantity' => 50],
            ],
        ])
        ->assertRedirect();

    $data['letter']->refresh();
    expect($data['letter']->status->value)->toBe('approved');
});

test('provincial cannot approve request letter', function () {
    $data = setupApprovalData();

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/approve", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'approved_quantity' => 70],
            ],
        ])
        ->assertForbidden();
});

test('lgu user cannot approve request letter', function () {
    $data = setupApprovalData();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/approve", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'approved_quantity' => 70],
            ],
        ])
        ->assertForbidden();
});

test('cannot approve a non-endorsed request letter', function () {
    $data = setupApprovalData();
    $data['letter']->update(['status' => 'pending']);

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/approve", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'approved_quantity' => 70],
            ],
        ])
        ->assertForbidden();
});

test('approved quantity cannot exceed endorsed quantity', function () {
    $data = setupApprovalData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/approve", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'approved_quantity' => 90],
                ['type' => AugmentationType::HygieneKits->value, 'approved_quantity' => 50],
            ],
        ])
        ->assertSessionHasErrors('augmentation_items.0.approved_quantity');
});

test('regional from different region cannot approve', function () {
    $data = setupApprovalData();
    $otherRegion = Region::factory()->create();
    $otherRegional = User::factory()->regional($otherRegion)->create();

    $this->actingAs($otherRegional)
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/approve", [
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'approved_quantity' => 70],
            ],
        ])
        ->assertForbidden();
});
