<?php

use App\Enums\AugmentationType;
use App\Models\CityMunicipality;
use App\Models\DeliveryPlan;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\RequestLetter;
use App\Models\User;

function setupDeliveryPlanData(): array
{
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser = User::factory()->lgu($lgu)->create();
    $escort = User::factory()->escort()->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($lgu->id);

    $letter = RequestLetter::factory()->create([
        'incident_id' => $incident->id,
        'user_id' => $lguUser->id,
        'city_municipality_id' => $lgu->id,
        'augmentation_items' => [
            ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 100, 'endorsed_quantity' => 80, 'approved_quantity' => 70],
            ['type' => AugmentationType::HygieneKits->value, 'quantity' => 50, 'endorsed_quantity' => 50, 'approved_quantity' => 40],
        ],
        'status' => 'approved',
        'endorsed_by' => $provincial->id,
        'endorsed_at' => now(),
        'approved_by' => $regional->id,
        'approved_at' => now(),
    ]);

    return compact('region', 'province', 'lgu', 'admin', 'regional', 'provincial', 'lguUser', 'escort', 'incident', 'letter');
}

test('regional can create a delivery plan', function () {
    $data = setupDeliveryPlanData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/delivery-plan", [
            'plan_items' => [
                [
                    'type' => AugmentationType::FamilyFoodPacks->value,
                    'batches' => [
                        ['quantity' => 40, 'delivery_date' => '2026-03-05', 'escort_user_id' => $data['escort']->id],
                        ['quantity' => 30, 'delivery_date' => '2026-03-06', 'escort_user_id' => null],
                    ],
                ],
                [
                    'type' => AugmentationType::HygieneKits->value,
                    'batches' => [
                        ['quantity' => 40, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                    ],
                ],
            ],
            'notes' => 'Split FFPs across two days',
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('delivery_plans', 1);

    $plan = DeliveryPlan::query()->first();
    expect($plan->plan_items)->toHaveCount(2);
    expect($plan->plan_items[0]['batches'])->toHaveCount(2);
    expect($plan->notes)->toBe('Split FFPs across two days');
    expect($plan->created_by)->toBe($data['regional']->id);
});

test('admin can create a delivery plan', function () {
    $data = setupDeliveryPlanData();

    $this->actingAs($data['admin'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/delivery-plan", [
            'plan_items' => [
                [
                    'type' => AugmentationType::FamilyFoodPacks->value,
                    'batches' => [
                        ['quantity' => 70, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                    ],
                ],
                [
                    'type' => AugmentationType::HygieneKits->value,
                    'batches' => [
                        ['quantity' => 40, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                    ],
                ],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('delivery_plans', 1);
});

test('provincial cannot create a delivery plan', function () {
    $data = setupDeliveryPlanData();

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/delivery-plan", [
            'plan_items' => [
                [
                    'type' => AugmentationType::FamilyFoodPacks->value,
                    'batches' => [
                        ['quantity' => 70, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                    ],
                ],
            ],
        ])
        ->assertForbidden();
});

test('lgu user cannot create a delivery plan', function () {
    $data = setupDeliveryPlanData();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/delivery-plan", [
            'plan_items' => [
                [
                    'type' => AugmentationType::FamilyFoodPacks->value,
                    'batches' => [
                        ['quantity' => 70, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                    ],
                ],
            ],
        ])
        ->assertForbidden();
});

test('plan quantity cannot exceed approved quantity', function () {
    $data = setupDeliveryPlanData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/delivery-plan", [
            'plan_items' => [
                [
                    'type' => AugmentationType::FamilyFoodPacks->value,
                    'batches' => [
                        ['quantity' => 50, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                        ['quantity' => 30, 'delivery_date' => '2026-03-06', 'escort_user_id' => null],
                    ],
                ],
            ],
        ])
        ->assertSessionHasErrors('plan_items.0');
});

test('saving a new plan replaces the existing one', function () {
    $data = setupDeliveryPlanData();

    // Create first plan
    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/delivery-plan", [
            'plan_items' => [
                [
                    'type' => AugmentationType::FamilyFoodPacks->value,
                    'batches' => [
                        ['quantity' => 70, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                    ],
                ],
            ],
            'notes' => 'First plan',
        ])
        ->assertRedirect();

    // Create second plan — should replace
    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/delivery-plan", [
            'plan_items' => [
                [
                    'type' => AugmentationType::FamilyFoodPacks->value,
                    'batches' => [
                        ['quantity' => 35, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                        ['quantity' => 35, 'delivery_date' => '2026-03-06', 'escort_user_id' => null],
                    ],
                ],
            ],
            'notes' => 'Updated plan',
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('delivery_plans', 1);

    $plan = DeliveryPlan::query()->first();
    expect($plan->notes)->toBe('Updated plan');
    expect($plan->plan_items[0]['batches'])->toHaveCount(2);
});

test('regional can delete a delivery plan', function () {
    $data = setupDeliveryPlanData();

    $plan = DeliveryPlan::query()->create([
        'request_letter_id' => $data['letter']->id,
        'created_by' => $data['regional']->id,
        'plan_items' => [
            [
                'type' => AugmentationType::FamilyFoodPacks->value,
                'batches' => [
                    ['quantity' => 70, 'delivery_date' => '2026-03-05', 'escort_user_id' => null],
                ],
            ],
        ],
    ]);

    $this->actingAs($data['regional'])
        ->delete("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/delivery-plan/{$plan->id}")
        ->assertRedirect();

    $this->assertDatabaseCount('delivery_plans', 0);
});
