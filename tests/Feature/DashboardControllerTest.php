<?php

use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;

function setupDashboardData(): array
{
    $region = Region::factory()->create();
    $province1 = Province::factory()->create(['region_id' => $region->id]);
    $province2 = Province::factory()->create(['region_id' => $region->id]);
    $lgu1 = CityMunicipality::factory()->create(['province_id' => $province1->id]);
    $lgu2 = CityMunicipality::factory()->create(['province_id' => $province2->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial = User::factory()->provincial($province1)->create();
    $lguUser = User::factory()->lgu($lgu1)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach([$lgu1->id, $lgu2->id]);

    return compact('admin', 'regional', 'provincial', 'lguUser', 'incident', 'region', 'province1', 'province2', 'lgu1', 'lgu2');
}

test('lgu user sees report counts scoped to their own reports', function () {
    $data = setupDashboardData();

    // Reports by this LGU user (different sequence numbers to avoid unique constraint)
    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'sequence_number' => 0,
        'status' => 'draft',
    ]);
    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'sequence_number' => 1,
        'status' => 'for_validation',
    ]);
    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'sequence_number' => 2,
        'status' => 'validated',
    ]);

    // Report by another LGU user (should NOT be counted)
    $otherLguUser = User::factory()->lgu($data['lgu2'])->create();
    Report::factory()->create([
        'user_id' => $otherLguUser->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu2']->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($data['lguUser'])->get('/dashboard');

    $response->assertSuccessful();
    $props = $response->original->getData()['page']['props'];
    expect($props['reportCounts']['draft'])->toBe(1)
        ->and($props['reportCounts']['for_validation'])->toBe(1)
        ->and($props['reportCounts']['validated'])->toBe(1)
        ->and($props['reportCounts']['returned'])->toBe(0);
});

test('provincial user sees report counts scoped to their province', function () {
    $data = setupDashboardData();

    // Report in province1 (provincial user's province)
    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'for_validation',
    ]);

    // Report in province2 (different province, should NOT be counted)
    $otherLguUser = User::factory()->lgu($data['lgu2'])->create();
    Report::factory()->create([
        'user_id' => $otherLguUser->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu2']->id,
        'status' => 'for_validation',
    ]);

    $response = $this->actingAs($data['provincial'])->get('/dashboard');

    $response->assertSuccessful();
    $props = $response->original->getData()['page']['props'];
    expect($props['reportCounts']['for_validation'])->toBe(1);
});

test('regional user sees report counts scoped to their region', function () {
    $data = setupDashboardData();

    // Reports in the region
    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'validated',
    ]);

    // Report in a different region (should NOT be counted)
    $otherRegion = Region::factory()->create();
    $otherProvince = Province::factory()->create(['region_id' => $otherRegion->id]);
    $otherLgu = CityMunicipality::factory()->create(['province_id' => $otherProvince->id]);
    $otherUser = User::factory()->lgu($otherLgu)->create();
    Report::factory()->create([
        'user_id' => $otherUser->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $otherLgu->id,
        'status' => 'validated',
    ]);

    $response = $this->actingAs($data['regional'])->get('/dashboard');

    $response->assertSuccessful();
    $props = $response->original->getData()['page']['props'];
    expect($props['reportCounts']['validated'])->toBe(1);
});

test('admin user sees all report counts', function () {
    $data = setupDashboardData();

    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'draft',
    ]);

    // Report from completely different region
    $otherRegion = Region::factory()->create();
    $otherProvince = Province::factory()->create(['region_id' => $otherRegion->id]);
    $otherLgu = CityMunicipality::factory()->create(['province_id' => $otherProvince->id]);
    $otherUser = User::factory()->lgu($otherLgu)->create();
    Report::factory()->create([
        'user_id' => $otherUser->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $otherLgu->id,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($data['admin'])->get('/dashboard');

    $response->assertSuccessful();
    $props = $response->original->getData()['page']['props'];
    expect($props['reportCounts']['draft'])->toBe(2);
});

test('lgu user does not receive reportActivity', function () {
    $data = setupDashboardData();

    $response = $this->actingAs($data['lguUser'])->get('/dashboard');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->missing('reportActivity')
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('reportActivity', null)
            )
        );
});

test('provincial user receives reporting coverage with correct lgu counts', function () {
    $data = setupDashboardData();

    // One LGU in province1 has submitted a report
    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'for_validation',
    ]);

    $response = $this->actingAs($data['provincial'])->get('/dashboard');

    $response->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->missing('reportActivity')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('reportActivity')
                ->where('reportActivity.0.incident_id', $data['incident']->id)
                ->where('reportActivity.0.total_lgus', 1) // only lgu1 is in province1
                ->where('reportActivity.0.reporting_lgus', 1)
            )
        );
});
