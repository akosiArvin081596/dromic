<?php

use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;

function setupAuthData(): array
{
    $region = Region::factory()->create();
    $province1 = Province::factory()->create(['region_id' => $region->id]);
    $province2 = Province::factory()->create(['region_id' => $region->id]);
    $lgu1 = CityMunicipality::factory()->create(['province_id' => $province1->id]);
    $lgu2 = CityMunicipality::factory()->create(['province_id' => $province2->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial1 = User::factory()->provincial($province1)->create();
    $lguUser1 = User::factory()->lgu($lgu1)->create();
    $lguUser2 = User::factory()->lgu($lgu2)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach([$lgu1->id, $lgu2->id]);

    return compact('admin', 'regional', 'provincial1', 'lguUser1', 'lguUser2', 'incident', 'region', 'province1', 'province2', 'lgu1', 'lgu2');
}

// Dashboard access
test('all roles can access dashboard', function () {
    $data = setupAuthData();

    $this->actingAs($data['admin'])->get('/dashboard')->assertSuccessful();
    $this->actingAs($data['regional'])->get('/dashboard')->assertSuccessful();
    $this->actingAs($data['provincial1'])->get('/dashboard')->assertSuccessful();
    $this->actingAs($data['lguUser1'])->get('/dashboard')->assertSuccessful();
});

// Incident creation authorization
test('provincial user cannot create incidents', function () {
    $data = setupAuthData();

    $this->actingAs($data['provincial1'])
        ->post('/incidents', [
            'category' => 'flood',
            'type' => 'massive',
            'city_municipality_ids' => [$data['lgu1']->id],
        ])
        ->assertForbidden();
});

// Cross-LGU report access
test('lgu user cannot view report from another lgu', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'draft',
    ]);

    // lguUser2 is from lgu2, should not be able to edit lgu1's report
    $this->actingAs($data['lguUser2'])
        ->get("/incidents/{$data['incident']->id}/reports/{$report->id}/edit")
        ->assertForbidden();
});

test('lgu user cannot update report from another lgu', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'draft',
    ]);

    $this->actingAs($data['lguUser2'])
        ->put("/incidents/{$data['incident']->id}/reports/{$report->id}", validReportData())
        ->assertForbidden();
});

test('lgu user cannot delete report from another lgu', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'draft',
    ]);

    $this->actingAs($data['lguUser2'])
        ->delete("/incidents/{$data['incident']->id}/reports/{$report->id}")
        ->assertForbidden();
});

// Admin report access
test('admin can view any report', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
    ]);

    $this->actingAs($data['admin'])
        ->get("/incidents/{$data['incident']->id}/reports/{$report->id}")
        ->assertSuccessful();
});

// Admin cannot create reports (only LGU can)
test('admin cannot create reports', function () {
    $data = setupAuthData();

    $this->actingAs($data['admin'])
        ->post("/incidents/{$data['incident']->id}/reports", validReportData())
        ->assertForbidden();
});

// Role middleware on consolidated route
test('role middleware blocks lgu from consolidated view', function () {
    $data = setupAuthData();

    $this->actingAs($data['lguUser1'])
        ->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertForbidden();
});

test('role middleware allows admin to consolidated view', function () {
    $data = setupAuthData();

    $this->actingAs($data['admin'])
        ->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertSuccessful();
});

test('role middleware allows provincial to consolidated view', function () {
    $data = setupAuthData();

    $this->actingAs($data['provincial1'])
        ->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertSuccessful();
});

// Provincial scoping
test('provincial user cannot view incident from another province', function () {
    $data = setupAuthData();

    $otherProvince = Province::factory()->create();
    $otherLgu = CityMunicipality::factory()->create(['province_id' => $otherProvince->id]);
    $otherIncident = Incident::factory()->create(['created_by' => $data['admin']->id]);
    $otherIncident->cityMunicipalities()->attach($otherLgu->id);

    $this->actingAs($data['provincial1'])
        ->get("/incidents/{$otherIncident->id}")
        ->assertForbidden();
});

// Validated report cannot be edited or deleted
test('lgu user cannot edit validated report', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'validated',
    ]);

    $this->actingAs($data['lguUser1'])
        ->get("/incidents/{$data['incident']->id}/reports/{$report->id}/edit")
        ->assertForbidden();
});

test('lgu user cannot delete validated report', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'validated',
    ]);

    $this->actingAs($data['lguUser1'])
        ->delete("/incidents/{$data['incident']->id}/reports/{$report->id}")
        ->assertForbidden();
});

// Regional role tests
test('regional user can view incidents in their region', function () {
    $data = setupAuthData();

    $this->actingAs($data['regional'])
        ->get("/incidents/{$data['incident']->id}")
        ->assertSuccessful();
});

test('regional user cannot view incident from another region', function () {
    $data = setupAuthData();

    $otherRegion = Region::factory()->create();
    $otherProvince = Province::factory()->create(['region_id' => $otherRegion->id]);
    $otherLgu = CityMunicipality::factory()->create(['province_id' => $otherProvince->id]);
    $otherIncident = Incident::factory()->create(['created_by' => $data['admin']->id]);
    $otherIncident->cityMunicipalities()->attach($otherLgu->id);

    $this->actingAs($data['regional'])
        ->get("/incidents/{$otherIncident->id}")
        ->assertForbidden();
});

test('regional user can create incidents', function () {
    $data = setupAuthData();

    $this->actingAs($data['regional'])
        ->get('/incidents/create')
        ->assertSuccessful();
});

test('regional user can view validated report from lgu in their region', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'validated',
    ]);

    $this->actingAs($data['regional'])
        ->get("/incidents/{$data['incident']->id}/reports/{$report->id}")
        ->assertSuccessful();
});

test('regional user cannot view non-validated report from their region', function () {
    $data = setupAuthData();

    foreach (['draft', 'for_validation', 'returned'] as $seq => $status) {
        $report = Report::factory()->create([
            'user_id' => $data['lguUser1']->id,
            'incident_id' => $data['incident']->id,
            'city_municipality_id' => $data['lgu1']->id,
            'sequence_number' => $seq,
            'status' => $status,
        ]);

        $this->actingAs($data['regional'])
            ->get("/incidents/{$data['incident']->id}/reports/{$report->id}")
            ->assertForbidden();
    }
});

test('regional user only sees validated reports in index', function () {
    $data = setupAuthData();

    $validatedReport = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'sequence_number' => 0,
        'status' => 'validated',
    ]);

    Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'sequence_number' => 1,
        'status' => 'draft',
        'report_number' => 'DROMIC-TEST-DRAFT',
    ]);

    Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'sequence_number' => 2,
        'status' => 'for_validation',
        'report_number' => 'DROMIC-TEST-FV',
    ]);

    $response = $this->actingAs($data['regional'])
        ->get("/incidents/{$data['incident']->id}/reports")
        ->assertSuccessful();

    $reports = $response->original->getData()['page']['props']['reports']['data'];
    $reportIds = collect($reports)->pluck('id')->all();

    expect($reportIds)->toContain($validatedReport->id);
    expect(count($reportIds))->toBe(1);
});

test('regional user cannot create reports', function () {
    $data = setupAuthData();

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/reports", validReportData())
        ->assertForbidden();
});

test('regional user cannot validate reports', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/validate")
        ->assertForbidden();
});

test('role middleware allows regional to consolidated view', function () {
    $data = setupAuthData();

    $this->actingAs($data['regional'])
        ->get("/incidents/{$data['incident']->id}/consolidated")
        ->assertSuccessful();
});

// --- Return report authorization tests ---

test('regional cannot return reports', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Missing data.',
        ])
        ->assertForbidden();
});

test('admin cannot return reports', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['admin'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Missing data.',
        ])
        ->assertForbidden();
});

test('lgu cannot return reports', function () {
    $data = setupAuthData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser1']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu1']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['lguUser1'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Missing data.',
        ])
        ->assertForbidden();
});

// --- Massive incident (no LGUs) visibility tests ---

test('lgu user can view massive incident with no lgus in same region', function () {
    $data = setupAuthData();

    $massiveIncident = Incident::factory()->create(['created_by' => $data['regional']->id]);
    // No city_municipalities attached — massive incident

    $this->actingAs($data['lguUser1'])
        ->get("/incidents/{$massiveIncident->id}")
        ->assertSuccessful();
});

test('provincial user can view massive incident with no lgus in same region', function () {
    $data = setupAuthData();

    $massiveIncident = Incident::factory()->create(['created_by' => $data['regional']->id]);

    $this->actingAs($data['provincial1'])
        ->get("/incidents/{$massiveIncident->id}")
        ->assertSuccessful();
});

test('regional user can view massive incident with no lgus in same region', function () {
    $data = setupAuthData();

    $massiveIncident = Incident::factory()->create(['created_by' => $data['regional']->id]);

    $this->actingAs($data['regional'])
        ->get("/incidents/{$massiveIncident->id}")
        ->assertSuccessful();
});

test('user from different region cannot view massive incident with no lgus', function () {
    $data = setupAuthData();

    $massiveIncident = Incident::factory()->create(['created_by' => $data['regional']->id]);

    $otherRegion = Region::factory()->create();
    $otherRegional = User::factory()->regional($otherRegion)->create();

    $this->actingAs($otherRegional)
        ->get("/incidents/{$massiveIncident->id}")
        ->assertForbidden();
});

test('massive incident with no lgus appears on incidents index for users in same region', function () {
    $data = setupAuthData();

    $massiveIncident = Incident::factory()->create([
        'created_by' => $data['regional']->id,
        'name' => 'Region-Wide Typhoon',
    ]);

    $response = $this->actingAs($data['lguUser1'])
        ->get('/incidents')
        ->assertSuccessful();

    $incidents = $response->original->getData()['page']['props']['incidents']['data'];
    $incidentIds = collect($incidents)->pluck('id')->all();
    expect($incidentIds)->toContain($massiveIncident->id);
});

test('provincial cannot return report from another province', function () {
    $data = setupAuthData();

    $otherProvince = Province::factory()->create();
    $otherLgu = CityMunicipality::factory()->create(['province_id' => $otherProvince->id]);
    $otherLguUser = User::factory()->lgu($otherLgu)->create();
    $data['incident']->cityMunicipalities()->attach($otherLgu->id);

    $report = Report::factory()->create([
        'user_id' => $otherLguUser->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $otherLgu->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['provincial1'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Missing data.',
        ])
        ->assertForbidden();
});
