<?php

use App\Enums\ReportType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Report;
use App\Models\User;

function setupConsolidatedData(): array
{
    $province = Province::factory()->create();
    $lgu1 = CityMunicipality::factory()->create(['province_id' => $province->id]);
    $lgu2 = CityMunicipality::factory()->create(['province_id' => $province->id]);

    $admin = User::factory()->admin()->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser = User::factory()->lgu($lgu1)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach([$lgu1->id, $lgu2->id]);

    // Create submitted reports for both LGUs
    $lgu1User = User::factory()->lgu($lgu1)->create();
    $lgu2User = User::factory()->lgu($lgu2)->create();

    Report::factory()->create([
        'user_id' => $lgu1User->id,
        'incident_id' => $incident->id,
        'city_municipality_id' => $lgu1->id,
        'report_type' => ReportType::Initial,
        'sequence_number' => 0,
        'status' => 'for_validation',
        'affected_areas' => [
            ['barangay' => 'Brgy A', 'families' => 100, 'persons' => 500],
        ],
        'damaged_houses' => [
            ['barangay' => 'Brgy A', 'totally_damaged' => 5, 'partially_damaged' => 10, 'estimated_cost' => 500000],
        ],
    ]);

    Report::factory()->create([
        'user_id' => $lgu2User->id,
        'incident_id' => $incident->id,
        'city_municipality_id' => $lgu2->id,
        'report_type' => ReportType::Initial,
        'sequence_number' => 0,
        'status' => 'for_validation',
        'affected_areas' => [
            ['barangay' => 'Brgy B', 'families' => 200, 'persons' => 1000],
        ],
        'damaged_houses' => [
            ['barangay' => 'Brgy B', 'totally_damaged' => 10, 'partially_damaged' => 20, 'estimated_cost' => 1000000],
        ],
    ]);

    return compact('admin', 'provincial', 'lguUser', 'incident', 'province', 'lgu1', 'lgu2');
}

test('admin can view consolidated report', function () {
    $data = setupConsolidatedData();

    $this->actingAs($data['admin'])
        ->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertSuccessful();
});

test('provincial can view consolidated report', function () {
    $data = setupConsolidatedData();

    $this->actingAs($data['provincial'])
        ->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertSuccessful();
});

test('lgu cannot view consolidated report', function () {
    $data = setupConsolidatedData();

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertForbidden();
});

test('unauthenticated user cannot view consolidated report', function () {
    $data = setupConsolidatedData();

    $this->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertRedirect('/login');
});

test('consolidated report aggregates totals from all LGU reports', function () {
    $data = setupConsolidatedData();

    $response = $this->actingAs($data['admin'])
        ->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertSuccessful();

    $page = $response->original->getData()['page'];
    $props = $page['props'];

    $cutoffs = $props['cutoffs'];
    expect($cutoffs)->not->toBeEmpty();

    // Check the latest (last) cut-off totals
    $latestTotals = end($cutoffs)['totals'];

    expect($latestTotals['affected_families'])->toBe(300)
        ->and($latestTotals['affected_persons'])->toBe(1500)
        ->and($latestTotals['totally_damaged'])->toBe(15)
        ->and($latestTotals['partially_damaged'])->toBe(30)
        ->and($latestTotals['estimated_cost'])->toBe(1500000);
});

test('consolidated report excludes draft reports', function () {
    $province = Province::factory()->create();
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);
    $admin = User::factory()->admin()->create();
    $lguUser = User::factory()->lgu($lgu)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($lgu->id);

    Report::factory()->create([
        'user_id' => $lguUser->id,
        'incident_id' => $incident->id,
        'city_municipality_id' => $lgu->id,
        'report_type' => ReportType::Initial,
        'sequence_number' => 0,
        'status' => 'draft',
        'affected_areas' => [
            ['barangay' => 'Brgy C', 'families' => 999, 'persons' => 9999],
        ],
    ]);

    $response = $this->actingAs($admin)
        ->get("/incidents/{$incident->id}/consolidated")
        ->assertSuccessful();

    $page = $response->original->getData()['page'];
    $props = $page['props'];

    expect($props['cutoffs'])->toBeEmpty();
});

test('consolidated report uses latest report per LGU', function () {
    $province = Province::factory()->create();
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);
    $admin = User::factory()->admin()->create();
    $lguUser = User::factory()->lgu($lgu)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($lgu->id);

    // Initial report — same cut-off as progress report
    Report::factory()->create([
        'user_id' => $lguUser->id,
        'incident_id' => $incident->id,
        'city_municipality_id' => $lgu->id,
        'report_type' => ReportType::Initial,
        'sequence_number' => 0,
        'report_date' => '2026-02-20',
        'report_time' => '12:00 PM',
        'status' => 'for_validation',
        'affected_areas' => [
            ['barangay' => 'Brgy X', 'families' => 50, 'persons' => 250],
        ],
    ]);

    // Progress report with updated numbers (should be used — higher sequence in same cut-off)
    Report::factory()->create([
        'user_id' => $lguUser->id,
        'incident_id' => $incident->id,
        'city_municipality_id' => $lgu->id,
        'report_type' => ReportType::Progress,
        'sequence_number' => 1,
        'report_date' => '2026-02-20',
        'report_time' => '12:00 PM',
        'status' => 'for_validation',
        'affected_areas' => [
            ['barangay' => 'Brgy X', 'families' => 150, 'persons' => 750],
        ],
    ]);

    $response = $this->actingAs($admin)
        ->get("/incidents/{$incident->id}/consolidated")
        ->assertSuccessful();

    $page = $response->original->getData()['page'];
    $props = $page['props'];

    $cutoffs = $props['cutoffs'];
    expect($cutoffs)->not->toBeEmpty();

    // The latest cut-off should have the progress report data
    $latestCutoff = end($cutoffs);
    expect($latestCutoff['totals']['affected_families'])->toBe(150)
        ->and($latestCutoff['totals']['affected_persons'])->toBe(750);
});
