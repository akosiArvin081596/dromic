<?php

use App\Enums\ReportType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;

function validReportData(): array
{
    return [
        'report_date' => '2026-01-15',
        'report_time' => '6:00 AM',
        'situation_overview' => 'Test situation overview.',
        'status' => 'draft',
        'affected_areas' => [
            ['barangay' => 'Brgy. Test', 'families' => 10, 'persons' => 50],
        ],
        'inside_evacuation_centers' => [],
        'age_distribution' => [
            '0-5' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '6-12' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '13-17' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '18-35' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '36-59' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '60-69' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            '70+' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
        ],
        'vulnerable_sectors' => [
            'Pregnant/Lactating' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            'Solo Parent' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            'PWD' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            'Indigenous People' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
            'Senior Citizen' => ['male_cum' => 0, 'male_now' => 0, 'female_cum' => 0, 'female_now' => 0],
        ],
        'outside_evacuation_centers' => [],
        'non_idps' => [],
        'damaged_houses' => [],
        'related_incidents' => [],
        'casualties_injured' => [],
        'casualties_missing' => [],
        'casualties_dead' => [],
        'infrastructure_damages' => [],
        'agriculture_damages' => [],
        'assistance_provided' => [],
        'class_suspensions' => [],
        'work_suspensions' => [],
        'lifelines_roads_bridges' => [],
        'lifelines_power' => [],
        'lifelines_water' => [],
        'lifelines_communication' => [],
        'seaport_status' => [],
        'airport_status' => [],
        'landport_status' => [],
        'stranded_passengers' => [],
        'calamity_declarations' => [],
        'preemptive_evacuations' => [],
        'gaps_challenges' => [],
        'response_actions' => null,
    ];
}

function setupIncidentWithLgu(): array
{
    $province = Province::factory()->create();
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id, 'psgc_code' => '9876543210']);
    $lguUser = User::factory()->lgu($lgu)->create();
    $admin = User::factory()->admin()->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($lgu->id);

    return compact('province', 'lgu', 'lguUser', 'admin', 'incident');
}

test('unauthenticated users are redirected from reports index', function () {
    $data = setupIncidentWithLgu();

    $this->get("/incidents/{$data['incident']->id}/reports")
        ->assertRedirect('/login');
});

test('authenticated user can view reports index', function () {
    $data = setupIncidentWithLgu();

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}/reports")
        ->assertSuccessful();
});

test('lgu user can view create report page for assigned incident', function () {
    $data = setupIncidentWithLgu();

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}/reports/create")
        ->assertSuccessful();
});

test('lgu user cannot create report for unassigned incident', function () {
    $data = setupIncidentWithLgu();

    $otherLgu = CityMunicipality::factory()->create();
    $otherUser = User::factory()->lgu($otherLgu)->create();

    $this->actingAs($otherUser)
        ->get("/incidents/{$data['incident']->id}/reports/create")
        ->assertForbidden();
});

test('admin cannot create reports', function () {
    $data = setupIncidentWithLgu();

    $this->actingAs($data['admin'])
        ->get("/incidents/{$data['incident']->id}/reports/create")
        ->assertForbidden();
});

test('lgu user can store a report and it auto-generates report number', function () {
    $data = setupIncidentWithLgu();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", validReportData())
        ->assertRedirect();

    $report = Report::where('incident_id', $data['incident']->id)->first();

    expect($report)->not->toBeNull()
        ->and($report->report_type)->toBe(ReportType::Initial)
        ->and($report->sequence_number)->toBe(0)
        ->and($report->report_number)->toContain('DROMIC-')
        ->and($report->report_number)->toContain('-IR')
        ->and($report->user_id)->toBe($data['lguUser']->id)
        ->and($report->city_municipality_id)->toBe($data['lgu']->id);
});

test('second report is auto-sequenced as progress', function () {
    $data = setupIncidentWithLgu();

    // Create initial report
    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'report_type' => ReportType::Initial,
        'sequence_number' => 0,
        'report_number' => "DROMIC-{$data['incident']->id}-9876543210-IR",
    ]);

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", validReportData())
        ->assertRedirect();

    $progress = Report::where('incident_id', $data['incident']->id)
        ->where('report_type', ReportType::Progress)
        ->first();

    expect($progress)->not->toBeNull()
        ->and($progress->sequence_number)->toBe(1)
        ->and($progress->report_number)->toContain('-PR1');
});

test('lgu user can view a report', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
    ]);

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}/reports/{$report->id}")
        ->assertSuccessful();
});

test('lgu user can edit own draft report', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'draft',
    ]);

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}/reports/{$report->id}/edit")
        ->assertSuccessful();
});

test('lgu user cannot edit for_validation report', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}/reports/{$report->id}/edit")
        ->assertForbidden();
});

test('lgu user can update own draft report', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'draft',
    ]);

    $updateData = validReportData();
    $updateData['situation_overview'] = 'Updated overview';

    $this->actingAs($data['lguUser'])
        ->put("/incidents/{$data['incident']->id}/reports/{$report->id}", $updateData)
        ->assertRedirect();

    $report->refresh();
    expect($report->situation_overview)->toBe('Updated overview');
});

test('lgu user can delete own draft report', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'draft',
    ]);

    $this->actingAs($data['lguUser'])
        ->delete("/incidents/{$data['incident']->id}/reports/{$report->id}")
        ->assertRedirect();

    $this->assertDatabaseMissing('reports', ['id' => $report->id]);
});

test('lgu user cannot delete for_validation report', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['lguUser'])
        ->delete("/incidents/{$data['incident']->id}/reports/{$report->id}")
        ->assertForbidden();
});

test('store validates required fields', function () {
    $data = setupIncidentWithLgu();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", [])
        ->assertSessionHasErrors(['affected_areas']);
});

test('store validates cumulative vs current for inside ECs', function () {
    $data = setupIncidentWithLgu();

    $reportData = validReportData();
    $reportData['inside_evacuation_centers'] = [
        [
            'barangay' => 'Brgy. Test',
            'ec_name' => 'Test EC',
            'families_cum' => 10,
            'families_now' => 20,
            'persons_cum' => 50,
            'persons_now' => 100,
            'origin' => 'Brgy. Test',
            'remarks' => '',
        ],
    ];

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $reportData)
        ->assertSessionHasErrors();
});

test('store validates persons cannot be less than families in affected areas', function () {
    $data = setupIncidentWithLgu();

    $reportData = validReportData();
    $reportData['affected_areas'] = [
        ['barangay' => 'Brgy. Test', 'families' => 10, 'persons' => 5],
    ];

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $reportData)
        ->assertSessionHasErrors(['affected_areas.0.persons']);
});

test('store validates persons cannot be less than families in inside ECs', function () {
    $data = setupIncidentWithLgu();

    $reportData = validReportData();
    $reportData['inside_evacuation_centers'] = [
        [
            'barangay' => 'Brgy. Test',
            'ec_name' => 'Test EC',
            'families_cum' => 10,
            'families_now' => 5,
            'persons_cum' => 5,
            'persons_now' => 3,
            'origin' => 'Brgy. Test',
            'remarks' => '',
        ],
    ];

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $reportData)
        ->assertSessionHasErrors([
            'inside_evacuation_centers.0.persons_cum',
            'inside_evacuation_centers.0.persons_now',
        ]);
});

test('store validates persons cannot be less than families in outside ECs', function () {
    $data = setupIncidentWithLgu();

    $reportData = validReportData();
    $reportData['outside_evacuation_centers'] = [
        [
            'barangay' => 'Brgy. Test',
            'families_cum' => 10,
            'families_now' => 5,
            'persons_cum' => 5,
            'persons_now' => 3,
            'origin' => 'Brgy. Test',
        ],
    ];

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $reportData)
        ->assertSessionHasErrors([
            'outside_evacuation_centers.0.persons_cum',
            'outside_evacuation_centers.0.persons_now',
        ]);
});

test('store validates persons cannot exist without families in affected areas', function () {
    $data = setupIncidentWithLgu();

    $reportData = validReportData();
    $reportData['affected_areas'] = [
        ['barangay' => 'Brgy. Test', 'families' => 0, 'persons' => 2],
    ];

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $reportData)
        ->assertSessionHasErrors(['affected_areas.0.persons']);
});

test('store validates persons cannot exist without families in inside ECs', function () {
    $data = setupIncidentWithLgu();

    $reportData = validReportData();
    $reportData['inside_evacuation_centers'] = [
        [
            'barangay' => 'Brgy. Test',
            'ec_name' => 'Test EC',
            'families_cum' => 0,
            'families_now' => 0,
            'persons_cum' => 2,
            'persons_now' => 0,
            'origin' => 'Brgy. Test',
            'remarks' => null,
        ],
    ];

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $reportData)
        ->assertSessionHasErrors(['inside_evacuation_centers.0.persons_cum']);
});

test('store validates persons cannot exist without families in outside ECs', function () {
    $data = setupIncidentWithLgu();

    $reportData = validReportData();
    $reportData['outside_evacuation_centers'] = [
        [
            'barangay' => 'Brgy. Test',
            'families_cum' => 0,
            'families_now' => 0,
            'persons_cum' => 0,
            'persons_now' => 3,
            'origin' => 'Brgy. Test',
        ],
    ];

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $reportData)
        ->assertSessionHasErrors(['outside_evacuation_centers.0.persons_now']);
});

test('terminal report can be created via is_terminal flag', function () {
    $data = setupIncidentWithLgu();

    // Create initial report first
    Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'report_type' => ReportType::Initial,
        'sequence_number' => 0,
        'report_number' => "DROMIC-{$data['incident']->id}-9876543210-IR",
    ]);

    $reportData = validReportData();
    $reportData['is_terminal'] = true;

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", $reportData)
        ->assertRedirect();

    $terminal = Report::where('incident_id', $data['incident']->id)
        ->where('report_type', ReportType::Terminal)
        ->first();

    expect($terminal)->not->toBeNull()
        ->and($terminal->report_number)->toContain('-TR');
});

test('regional user cannot create reports', function () {
    $data = setupIncidentWithLgu();

    $region = Region::factory()->create();
    $data['province']->update(['region_id' => $region->id]);
    $regional = User::factory()->regional($region)->create();

    $this->actingAs($regional)
        ->get("/incidents/{$data['incident']->id}/reports/create")
        ->assertForbidden();
});

test('regional user cannot store reports', function () {
    $data = setupIncidentWithLgu();

    $region = Region::factory()->create();
    $data['province']->update(['region_id' => $region->id]);
    $regional = User::factory()->regional($region)->create();

    $this->actingAs($regional)
        ->post("/incidents/{$data['incident']->id}/reports", validReportData())
        ->assertForbidden();
});

// --- Return report tests ---

test('provincial can return for_validation report with reason', function () {
    $data = setupIncidentWithLgu();
    $provincial = User::factory()->provincial($data['province'])->create();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($provincial)
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Incomplete data in affected areas section.',
        ])
        ->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe('returned')
        ->and($report->return_reason)->toBe('Incomplete data in affected areas section.');
});

test('return_reason is required when returning a report', function () {
    $data = setupIncidentWithLgu();
    $provincial = User::factory()->provincial($data['province'])->create();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($provincial)
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => '',
        ])
        ->assertSessionHasErrors(['return_reason']);
});

test('lgu user can edit returned report', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->returned()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
    ]);

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}/reports/{$report->id}/edit")
        ->assertSuccessful();
});

test('lgu resubmit clears return_reason', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->returned()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
    ]);

    $updateData = validReportData();
    $updateData['status'] = 'for_validation';

    $this->actingAs($data['lguUser'])
        ->put("/incidents/{$data['incident']->id}/reports/{$report->id}", $updateData)
        ->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe('for_validation')
        ->and($report->return_reason)->toBeNull();
});

test('lgu user cannot delete returned report', function () {
    $data = setupIncidentWithLgu();

    $report = Report::factory()->returned()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
    ]);

    $this->actingAs($data['lguUser'])
        ->delete("/incidents/{$data['incident']->id}/reports/{$report->id}")
        ->assertForbidden();
});

test('provincial cannot return draft report', function () {
    $data = setupIncidentWithLgu();
    $provincial = User::factory()->provincial($data['province'])->create();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'draft',
    ]);

    $this->actingAs($provincial)
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Some reason.',
        ])
        ->assertForbidden();
});

test('provincial cannot return validated report', function () {
    $data = setupIncidentWithLgu();
    $provincial = User::factory()->provincial($data['province'])->create();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'validated',
    ]);

    $this->actingAs($provincial)
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Some reason.',
        ])
        ->assertForbidden();
});
