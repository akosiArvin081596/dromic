<?php

use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;

function createGeoHierarchy(string $regionName = 'Caraga', string $provinceName = 'Agusan del Norte', string $municipalityName = 'Butuan City'): array
{
    $region = Region::factory()->create(['name' => $regionName]);
    $province = Province::factory()->create(['name' => $provinceName, 'region_id' => $region->id]);
    $municipality = CityMunicipality::factory()->create(['name' => $municipalityName, 'province_id' => $province->id]);
    $user = User::factory()->lgu($municipality)->create();

    return compact('region', 'province', 'municipality', 'user');
}

function createReportForIncident(Incident $incident, CityMunicipality $municipality, User $user, array $barangays): Report
{
    $affectedAreas = array_map(fn (string $brgy) => [
        'barangay' => $brgy,
        'families' => 10,
        'persons' => 50,
    ], $barangays);

    return Report::factory()->create([
        'incident_id' => $incident->id,
        'city_municipality_id' => $municipality->id,
        'user_id' => $user->id,
        'affected_areas' => $affectedAreas,
        'status' => 'draft',
    ]);
}

test('display_name equals name when no reports exist', function () {
    $incident = Incident::factory()->create(['name' => 'Fire Incident']);

    $incident->refreshDisplayName();

    expect($incident->fresh()->display_name)->toBe('Fire Incident');
});

test('display_name shows barangay and municipality for single affected area', function () {
    $geo = createGeoHierarchy();
    $incident = Incident::factory()->create(['name' => 'Fire Incident']);
    $incident->cityMunicipalities()->attach($geo['municipality']->id);

    createReportForIncident($incident, $geo['municipality'], $geo['user'], ['Brgy. San Juan']);

    expect($incident->fresh()->display_name)->toBe('Fire Incident at Brgy. San Juan, Butuan City, Agusan del Norte');
});

test('display_name adds Brgy prefix when not present', function () {
    $geo = createGeoHierarchy();
    $incident = Incident::factory()->create(['name' => 'Fire Incident']);
    $incident->cityMunicipalities()->attach($geo['municipality']->id);

    createReportForIncident($incident, $geo['municipality'], $geo['user'], ['San Juan']);

    expect($incident->fresh()->display_name)->toBe('Fire Incident at Brgy. San Juan, Butuan City, Agusan del Norte');
});

test('display_name shows municipality for multiple barangays in same municipality', function () {
    $geo = createGeoHierarchy();
    $incident = Incident::factory()->create(['name' => 'Fire Incident']);
    $incident->cityMunicipalities()->attach($geo['municipality']->id);

    createReportForIncident($incident, $geo['municipality'], $geo['user'], ['Brgy. San Juan', 'Brgy. Libertad']);

    expect($incident->fresh()->display_name)->toBe('Fire Incident at Butuan City, Agusan del Norte');
});

test('display_name shows province for multiple municipalities in same province', function () {
    $geo = createGeoHierarchy();
    $municipality2 = CityMunicipality::factory()->create(['name' => 'Cabadbaran City', 'province_id' => $geo['province']->id]);
    $user2 = User::factory()->lgu($municipality2)->create();

    $incident = Incident::factory()->create(['name' => 'Typhoon Aghon']);
    $incident->cityMunicipalities()->attach([$geo['municipality']->id, $municipality2->id]);

    createReportForIncident($incident, $geo['municipality'], $geo['user'], ['Brgy. San Juan']);
    createReportForIncident($incident, $municipality2, $user2, ['Brgy. Poblacion']);

    expect($incident->fresh()->display_name)->toBe('Typhoon Aghon affecting the Province of Agusan del Norte');
});

test('display_name shows region for multiple provinces', function () {
    $geo = createGeoHierarchy();
    $province2 = Province::factory()->create(['name' => 'Agusan del Sur', 'region_id' => $geo['region']->id]);
    $municipality2 = CityMunicipality::factory()->create(['name' => 'Prosperidad', 'province_id' => $province2->id]);
    $user2 = User::factory()->lgu($municipality2)->create();

    $incident = Incident::factory()->create(['name' => 'Typhoon Aghon']);
    $incident->cityMunicipalities()->attach([$geo['municipality']->id, $municipality2->id]);

    createReportForIncident($incident, $geo['municipality'], $geo['user'], ['Brgy. San Juan']);
    createReportForIncident($incident, $municipality2, $user2, ['Brgy. Poblacion']);

    expect($incident->fresh()->display_name)->toBe('Typhoon Aghon affecting Caraga');
});

test('observer fires on report create and updates display_name', function () {
    $geo = createGeoHierarchy();
    $incident = Incident::factory()->create(['name' => 'Fire Incident', 'display_name' => 'Fire Incident']);
    $incident->cityMunicipalities()->attach($geo['municipality']->id);

    createReportForIncident($incident, $geo['municipality'], $geo['user'], ['Brgy. San Juan']);

    expect($incident->fresh()->display_name)->toBe('Fire Incident at Brgy. San Juan, Butuan City, Agusan del Norte');
});

test('observer fires on report delete and reverts display_name', function () {
    $geo = createGeoHierarchy();
    $incident = Incident::factory()->create(['name' => 'Fire Incident', 'display_name' => 'Fire Incident']);
    $incident->cityMunicipalities()->attach($geo['municipality']->id);

    $report = createReportForIncident($incident, $geo['municipality'], $geo['user'], ['Brgy. San Juan']);

    expect($incident->fresh()->display_name)->toBe('Fire Incident at Brgy. San Juan, Butuan City, Agusan del Norte');

    $report->delete();

    expect($incident->fresh()->display_name)->toBe('Fire Incident');
});

test('observer fires on affected_areas update and recomputes display_name', function () {
    $geo = createGeoHierarchy();
    $incident = Incident::factory()->create(['name' => 'Fire Incident', 'display_name' => 'Fire Incident']);
    $incident->cityMunicipalities()->attach($geo['municipality']->id);

    $report = createReportForIncident($incident, $geo['municipality'], $geo['user'], ['Brgy. San Juan']);

    expect($incident->fresh()->display_name)->toBe('Fire Incident at Brgy. San Juan, Butuan City, Agusan del Norte');

    $report->update([
        'affected_areas' => [
            ['barangay' => 'Brgy. San Juan', 'families' => 10, 'persons' => 50],
            ['barangay' => 'Brgy. Libertad', 'families' => 5, 'persons' => 25],
        ],
    ]);

    expect($incident->fresh()->display_name)->toBe('Fire Incident at Butuan City, Agusan del Norte');
});

test('incident name edit recomputes display_name', function () {
    $geo = createGeoHierarchy();
    $admin = User::factory()->admin()->create();

    $incident = Incident::factory()->create(['name' => 'Fire Incident', 'display_name' => 'Fire Incident', 'created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($geo['municipality']->id);

    createReportForIncident($incident, $geo['municipality'], $geo['user'], ['Brgy. San Juan']);

    expect($incident->fresh()->display_name)->toBe('Fire Incident at Brgy. San Juan, Butuan City, Agusan del Norte');

    $this->actingAs($admin)->put("/incidents/{$incident->id}", [
        'category' => 'flood',
        'type' => 'massive',
        'status' => 'active',
        'city_municipality_ids' => [$geo['municipality']->id],
    ]);

    expect($incident->fresh()->display_name)->toBe('Flood at Brgy. San Juan, Butuan City, Agusan del Norte');
});

test('empty barangay strings are ignored', function () {
    $geo = createGeoHierarchy();
    $incident = Incident::factory()->create(['name' => 'Fire Incident']);
    $incident->cityMunicipalities()->attach($geo['municipality']->id);

    Report::factory()->create([
        'incident_id' => $incident->id,
        'city_municipality_id' => $geo['municipality']->id,
        'user_id' => $geo['user']->id,
        'affected_areas' => [
            ['barangay' => '', 'families' => 10, 'persons' => 50],
            ['barangay' => '  ', 'families' => 5, 'persons' => 25],
        ],
        'status' => 'draft',
    ]);

    expect($incident->fresh()->display_name)->toBe('Fire Incident');
});
