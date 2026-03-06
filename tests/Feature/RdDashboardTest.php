<?php

use App\Enums\RequestLetterStatus;
use App\Models\CityMunicipality;
use App\Models\Delivery;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\RequestLetter;
use App\Models\User;

function setupRdData(): array
{
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser = User::factory()->lgu($lgu)->create();
    $rd = User::factory()->regionalDirector($region)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id, 'status' => 'active']);
    $incident->cityMunicipalities()->attach([$lgu->id]);

    return compact('region', 'province', 'lgu', 'admin', 'regional', 'provincial', 'lguUser', 'rd', 'incident');
}

// RD can access the dashboard
test('regional director can access rd dashboard', function () {
    $data = setupRdData();

    $this->actingAs($data['rd'])
        ->get('/rd-dashboard')
        ->assertSuccessful();
});

// RD can access dashboard with a selected incident
test('regional director can view rd dashboard with selected incident', function () {
    $data = setupRdData();

    $this->actingAs($data['rd'])
        ->get("/rd-dashboard/{$data['incident']->id}")
        ->assertSuccessful();
});

// RD is redirected from /dashboard to /rd-dashboard
test('regional director is redirected from dashboard to rd dashboard', function () {
    $data = setupRdData();

    $this->actingAs($data['rd'])
        ->get('/dashboard')
        ->assertRedirect(route('rd-dashboard'));
});

// Non-RD users cannot access /rd-dashboard
test('non-rd users cannot access rd dashboard', function () {
    $data = setupRdData();

    $this->actingAs($data['admin'])->get('/rd-dashboard')->assertForbidden();
    $this->actingAs($data['regional'])->get('/rd-dashboard')->assertForbidden();
    $this->actingAs($data['provincial'])->get('/rd-dashboard')->assertForbidden();
    $this->actingAs($data['lguUser'])->get('/rd-dashboard')->assertForbidden();
});

// RD cannot access incidents index
test('regional director cannot access incidents', function () {
    $data = setupRdData();

    $this->actingAs($data['rd'])
        ->get('/incidents')
        ->assertForbidden();
});

// RD cannot view a specific incident
test('regional director cannot view a specific incident', function () {
    $data = setupRdData();

    $this->actingAs($data['rd'])
        ->get("/incidents/{$data['incident']->id}")
        ->assertForbidden();
});

// RD cannot create incidents
test('regional director cannot create incidents', function () {
    $data = setupRdData();

    $this->actingAs($data['rd'])
        ->post('/incidents', [
            'category' => 'flood',
            'type' => 'massive',
            'city_municipality_ids' => [$data['lgu']->id],
        ])
        ->assertForbidden();
});

// RD cannot see incidents from another region
test('regional director cannot see incidents from another region', function () {
    $data = setupRdData();

    $otherRegion = Region::factory()->create();
    $otherProvince = Province::factory()->create(['region_id' => $otherRegion->id]);
    $otherLgu = CityMunicipality::factory()->create(['province_id' => $otherProvince->id]);

    $otherIncident = Incident::factory()->create(['created_by' => $data['admin']->id, 'status' => 'active']);
    $otherIncident->cityMunicipalities()->attach([$otherLgu->id]);

    $this->actingAs($data['rd'])
        ->get("/rd-dashboard/{$otherIncident->id}")
        ->assertForbidden();
});

// Dashboard data includes impact totals
test('rd dashboard returns correct impact totals', function () {
    $data = setupRdData();

    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'validated',
        'affected_areas' => [
            ['barangay' => 'Brgy. A', 'families' => 10, 'persons' => 50],
            ['barangay' => 'Brgy. B', 'families' => 20, 'persons' => 100],
        ],
        'inside_evacuation_centers' => [
            ['barangay' => 'Brgy. A', 'ec_name' => 'School 1', 'families_cum' => 8, 'families_now' => 5, 'persons_cum' => 40, 'persons_now' => 25, 'origin' => 'Brgy. X'],
        ],
        'outside_evacuation_centers' => [
            ['barangay' => 'Brgy. A', 'families_cum' => 6, 'families_now' => 3, 'persons_cum' => 30, 'persons_now' => 15, 'origin' => 'Brgy. Y'],
        ],
    ]);

    $response = $this->actingAs($data['rd'])
        ->get("/rd-dashboard/{$data['incident']->id}");

    $response->assertSuccessful();
    $dashboardData = $response->original->getData()['page']['props']['dashboardData'];

    expect($dashboardData['impact']['affected_families'])->toBe(30)
        ->and($dashboardData['impact']['affected_persons'])->toBe(150)
        ->and($dashboardData['impact']['inside_ec_families_now'])->toBe(5)
        ->and($dashboardData['impact']['inside_ec_persons_now'])->toBe(25)
        ->and($dashboardData['impact']['outside_ec_families_now'])->toBe(3)
        ->and($dashboardData['impact']['outside_ec_persons_now'])->toBe(15)
        ->and($dashboardData['impact']['active_evacuation_centers'])->toBe(1);
});

// Augmentation summary is grouped by province
test('rd dashboard returns augmentation summary grouped by province', function () {
    $data = setupRdData();

    $requestLetter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => RequestLetterStatus::Approved,
        'augmentation_items' => [
            ['type' => 'family_food_packs', 'quantity' => 100, 'approved_quantity' => 80],
        ],
    ]);

    Delivery::factory()->create([
        'request_letter_id' => $requestLetter->id,
        'recorded_by' => $data['admin']->id,
        'delivery_items' => [
            ['type' => 'family_food_packs', 'quantity' => 30],
        ],
        'delivery_date' => now(),
    ]);

    $response = $this->actingAs($data['rd'])
        ->get("/rd-dashboard/{$data['incident']->id}");

    $response->assertSuccessful();
    $dashboardData = $response->original->getData()['page']['props']['dashboardData'];
    $augmentation = $dashboardData['augmentation'];

    expect($augmentation)->toHaveCount(1)
        ->and($augmentation[0]['province_id'])->toBe($data['province']->id)
        ->and($augmentation[0]['province_name'])->toBe($data['province']->name)
        ->and($augmentation[0]['items'])->toHaveCount(1)
        ->and($augmentation[0]['items'][0]['type'])->toBe('family_food_packs')
        ->and($augmentation[0]['items'][0]['approved'])->toBe(80)
        ->and($augmentation[0]['items'][0]['delivered'])->toBe(30)
        ->and($augmentation[0]['items'][0]['delivery_percent'])->toBe(37.5)
        ->and($augmentation[0]['lgus'])->toHaveCount(1)
        ->and($augmentation[0]['lgus'][0]['city_municipality_id'])->toBe($data['lgu']->id);
});

// Pending request letters are not included in augmentation
test('pending request letters are not included in augmentation summary', function () {
    $data = setupRdData();

    RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => RequestLetterStatus::Pending,
        'augmentation_items' => [
            ['type' => 'family_food_packs', 'quantity' => 100],
        ],
    ]);

    $response = $this->actingAs($data['rd'])
        ->get("/rd-dashboard/{$data['incident']->id}");

    $response->assertSuccessful();
    $dashboardData = $response->original->getData()['page']['props']['dashboardData'];

    expect($dashboardData['augmentation'])->toBeEmpty();
});
