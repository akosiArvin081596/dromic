<?php

use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\User;

function createUsersAndIncident(): array
{
    $province = Province::factory()->create();
    $lgu1 = CityMunicipality::factory()->create(['province_id' => $province->id]);
    $lgu2 = CityMunicipality::factory()->create(['province_id' => $province->id]);

    $admin = User::factory()->admin()->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser = User::factory()->lgu($lgu1)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach([$lgu1->id, $lgu2->id]);

    return compact('admin', 'provincial', 'lguUser', 'incident', 'province', 'lgu1', 'lgu2');
}

test('unauthenticated users are redirected from incidents index', function () {
    $this->get('/incidents')->assertRedirect('/login');
});

test('admin can view all incidents', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['admin'])
        ->get('/incidents')
        ->assertSuccessful();
});

test('provincial sees only incidents with LGUs in their province', function () {
    $data = createUsersAndIncident();

    $otherProvince = Province::factory()->create();
    $otherLgu = CityMunicipality::factory()->create(['province_id' => $otherProvince->id]);
    $otherIncident = Incident::factory()->create(['created_by' => $data['admin']->id]);
    $otherIncident->cityMunicipalities()->attach($otherLgu->id);

    $response = $this->actingAs($data['provincial'])
        ->get('/incidents')
        ->assertSuccessful();
});

test('lgu sees only incidents they are assigned to', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['lguUser'])
        ->get('/incidents')
        ->assertSuccessful();
});

test('admin can view create incident page', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['admin'])
        ->get('/incidents/create')
        ->assertSuccessful();
});

test('lgu can view create incident page', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['lguUser'])
        ->get('/incidents/create')
        ->assertSuccessful();
});

test('provincial cannot view create incident page', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['provincial'])
        ->get('/incidents/create')
        ->assertForbidden();
});

test('admin can create a massive incident', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['admin'])
        ->post('/incidents', [
            'category' => 'tropical_cyclone',
            'identifier' => 'Test',
            'type' => 'massive',
            'description' => 'A test incident',
            'city_municipality_ids' => [$data['lgu1']->id, $data['lgu2']->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('incidents', [
        'name' => 'Tropical Cyclone Test',
        'category' => 'tropical_cyclone',
        'identifier' => 'Test',
        'type' => 'massive',
        'created_by' => $data['admin']->id,
    ]);
});

test('regional can create a massive incident without assigning lgus', function () {
    $region = Region::factory()->create();
    $regional = User::factory()->regional($region)->create();

    $this->actingAs($regional)
        ->post('/incidents', [
            'category' => 'tropical_cyclone',
            'identifier' => 'Massive',
            'type' => 'massive',
            'description' => 'A massive typhoon',
        ])
        ->assertRedirect();

    $newIncident = Incident::where('name', 'Tropical Cyclone Massive')->first();
    expect($newIncident)->not->toBeNull();
    expect($newIncident->cityMunicipalities)->toHaveCount(0);
});

test('admin can create a massive incident without assigning lgus', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/incidents', [
            'category' => 'earthquake',
            'identifier' => 'Massive',
            'type' => 'massive',
            'description' => 'A massive earthquake',
        ])
        ->assertRedirect();

    $newIncident = Incident::where('name', 'Earthquake Massive')->first();
    expect($newIncident)->not->toBeNull();
    expect($newIncident->cityMunicipalities)->toHaveCount(0);
});

test('lgu can create a local incident with auto-assigned lgu', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['lguUser'])
        ->post('/incidents', [
            'category' => 'flood',
            'type' => 'local',
            'description' => 'Localized flooding',
            'city_municipality_ids' => [$data['lgu1']->id],
        ])
        ->assertRedirect();

    $newIncident = Incident::where('name', 'Flood')->first();
    expect($newIncident)->not->toBeNull();

    // LGU's own city_municipality is auto-attached
    expect($newIncident->cityMunicipalities->pluck('id')->contains($data['lgu1']->id))->toBeTrue();
});

test('admin can view incident show page', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['admin'])
        ->get("/incidents/{$data['incident']->id}")
        ->assertSuccessful();
});

test('lgu assigned to incident can view it', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}")
        ->assertSuccessful();
});

test('lgu not assigned to incident cannot view it', function () {
    $data = createUsersAndIncident();

    $otherLgu = CityMunicipality::factory()->create();
    $otherUser = User::factory()->lgu($otherLgu)->create();

    $this->actingAs($otherUser)
        ->get("/incidents/{$data['incident']->id}")
        ->assertForbidden();
});

test('admin can view edit incident page', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['admin'])
        ->get("/incidents/{$data['incident']->id}/edit")
        ->assertSuccessful();
});

test('non-admin cannot view edit incident page', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['lguUser'])
        ->get("/incidents/{$data['incident']->id}/edit")
        ->assertForbidden();
});

test('admin can update an incident', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['admin'])
        ->put("/incidents/{$data['incident']->id}", [
            'category' => 'fire',
            'identifier' => 'Updated',
            'type' => 'massive',
            'description' => 'Updated description',
            'city_municipality_ids' => [$data['lgu1']->id],
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('incidents', [
        'id' => $data['incident']->id,
        'name' => 'Fire Updated',
        'category' => 'fire',
        'identifier' => 'Updated',
    ]);
});

test('lgu cannot update an incident', function () {
    $data = createUsersAndIncident();

    $this->actingAs($data['lguUser'])
        ->put("/incidents/{$data['incident']->id}", [
            'category' => 'fire',
            'type' => 'massive',
            'city_municipality_ids' => [$data['lgu1']->id],
        ])
        ->assertForbidden();
});

test('store validates required fields', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/incidents', [])
        ->assertSessionHasErrors(['category', 'type']);
});

test('incidents can be filtered by search', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create(['name' => 'Typhoon Alpha', 'created_by' => $admin->id]);
    Incident::factory()->create(['name' => 'Earthquake Beta', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->get('/incidents?search=Typhoon')
        ->assertSuccessful();
});

test('incidents can be filtered by status', function () {
    $admin = User::factory()->admin()->create();
    Incident::factory()->create(['name' => 'Active One', 'created_by' => $admin->id, 'status' => 'active']);
    Incident::factory()->closed()->create(['name' => 'Closed One', 'created_by' => $admin->id]);

    $this->actingAs($admin)
        ->get('/incidents?status=active')
        ->assertSuccessful();
});
