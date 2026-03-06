<?php

use App\Enums\AugmentationType;
use App\Enums\UserType;
use App\Models\CityMunicipality;
use App\Models\Delivery;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\RequestLetter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function setupDeliveryData(): array
{
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->state(['user_type' => UserType::Rros])->create();
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

test('regional can record a delivery', function () {
    $data = setupDeliveryData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
                ['type' => AugmentationType::HygieneKits->value, 'quantity' => 20],
            ],
            'delivery_date' => '2026-03-03',
            'notes' => 'First delivery batch',
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('deliveries', 1);

    $data['letter']->refresh();
    expect($data['letter']->status->value)->toBe('delivering');
});

test('admin can record a delivery', function () {
    $data = setupDeliveryData();

    $this->actingAs($data['admin'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 70],
                ['type' => AugmentationType::HygieneKits->value, 'quantity' => 40],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertRedirect();

    $data['letter']->refresh();
    expect($data['letter']->status->value)->toBe('completed');
});

test('provincial cannot record a delivery', function () {
    $data = setupDeliveryData();

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertForbidden();
});

test('lgu user cannot record a delivery', function () {
    $data = setupDeliveryData();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertForbidden();
});

test('delivery quantity cannot exceed remaining balance', function () {
    $data = setupDeliveryData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 100],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertSessionHasErrors('delivery_items.0.quantity');
});

test('status transitions from approved to delivering to completed', function () {
    $data = setupDeliveryData();

    // First delivery — partial
    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
                ['type' => AugmentationType::HygieneKits->value, 'quantity' => 20],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertRedirect();

    $data['letter']->refresh();
    expect($data['letter']->status->value)->toBe('delivering');

    // Second delivery — remaining
    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 40],
                ['type' => AugmentationType::HygieneKits->value, 'quantity' => 20],
            ],
            'delivery_date' => '2026-03-04',
        ])
        ->assertRedirect();

    $data['letter']->refresh();
    expect($data['letter']->status->value)->toBe('completed');
    $this->assertDatabaseCount('deliveries', 2);
});

test('delivery can include an escort user', function () {
    $data = setupDeliveryData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'escort_user_id' => $data['escort']->id,
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertRedirect();

    $delivery = Delivery::query()->first();
    expect($delivery->escort_user_id)->toBe($data['escort']->id);
});

test('delivery can include attachments', function () {
    Storage::fake('local');
    $data = setupDeliveryData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
            ],
            'delivery_date' => '2026-03-03',
            'attachments' => [
                UploadedFile::fake()->image('receipt.jpg'),
                UploadedFile::fake()->create('report.pdf', 500, 'application/pdf'),
            ],
        ])
        ->assertRedirect();

    $delivery = Delivery::query()->first();
    expect($delivery->attachments)->toHaveCount(2);
    expect($delivery->attachments[0]->file_type)->toBe('photo');
    expect($delivery->attachments[1]->file_type)->toBe('document');
});

test('escort can update their assigned delivery', function () {
    $data = setupDeliveryData();

    $delivery = Delivery::factory()->create([
        'request_letter_id' => $data['letter']->id,
        'escort_user_id' => $data['escort']->id,
        'recorded_by' => $data['regional']->id,
        'delivery_items' => [
            ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
        ],
        'delivery_date' => '2026-03-03',
    ]);

    $this->actingAs($data['escort'])
        ->patch("/deliveries/{$delivery->id}", [
            'notes' => 'Updated notes from escort',
        ])
        ->assertRedirect();

    $delivery->refresh();
    expect($delivery->notes)->toBe('Updated notes from escort');
});

test('escort cannot update delivery not assigned to them', function () {
    $data = setupDeliveryData();
    $otherEscort = User::factory()->escort()->create();

    $delivery = Delivery::factory()->create([
        'request_letter_id' => $data['letter']->id,
        'escort_user_id' => $data['escort']->id,
        'recorded_by' => $data['regional']->id,
        'delivery_items' => [
            ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
        ],
        'delivery_date' => '2026-03-03',
    ]);

    $this->actingAs($otherEscort)
        ->patch("/deliveries/{$delivery->id}", [
            'notes' => 'Should not work',
        ])
        ->assertForbidden();
});

test('drims regional cannot record a delivery', function () {
    $data = setupDeliveryData();
    $drims = User::factory()->regional(Region::find($data['region']->id))->create();

    $this->actingAs($drims)
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertForbidden();
});

test('cannot record delivery on pending request letter', function () {
    $data = setupDeliveryData();
    $data['letter']->update(['status' => 'pending']);

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters/{$data['letter']->id}/deliveries", [
            'delivery_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 30],
            ],
            'delivery_date' => '2026-03-03',
        ])
        ->assertForbidden();
});
