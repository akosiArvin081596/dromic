<?php

use App\Enums\AugmentationType;
use App\Enums\UserType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\RequestLetter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function setupRequestLetterData(): array
{
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->state(['user_type' => UserType::Rros])->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser = User::factory()->lgu($lgu)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($lgu->id);

    return compact('region', 'province', 'lgu', 'admin', 'regional', 'provincial', 'lguUser', 'incident');
}

function createReportForLgu(array $data, int $affectedFamilies = 100): Report
{
    return Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'validated',
        'affected_areas' => [
            ['barangay' => 'Brgy. Test', 'families' => $affectedFamilies, 'persons' => $affectedFamilies * 4],
        ],
    ]);
}

// --- Authorization Tests ---

test('lgu user can upload request letter when report exists', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data);

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('request_letters', 1);

    $letter = RequestLetter::query()->first();
    Storage::disk('local')->assertExists($letter->file_path);
    expect($letter->status->value)->toBe('pending');
});

test('lgu user cannot upload request letter without existing report', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertForbidden();
});

test('admin cannot upload request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data);

    $this->actingAs($data['admin'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertForbidden();
});

test('provincial cannot upload request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data);

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertForbidden();
});

test('regional cannot upload request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data);

    $this->actingAs($data['regional'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertForbidden();
});

test('lgu user can upload request letter for massive incident in same region', function () {
    Storage::fake('local');
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);
    $lguUser = User::factory()->lgu($lgu)->create();
    $admin = User::factory()->admin()->create(['region_id' => $region->id]);

    // Massive incident — no city_municipalities attached
    $incident = Incident::factory()->create(['created_by' => $admin->id, 'type' => 'massive']);

    Report::factory()->create([
        'user_id' => $lguUser->id,
        'incident_id' => $incident->id,
        'city_municipality_id' => $lgu->id,
        'status' => 'validated',
        'affected_areas' => [['barangay' => 'Brgy. Test', 'families' => 100, 'persons' => 400]],
    ]);

    $this->actingAs($lguUser)
        ->post("/incidents/{$incident->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('request_letters', 1);
});

test('notification is sent to admin, regional, and provincial on upload', function () {
    Storage::fake('local');
    Illuminate\Support\Facades\Notification::fake();

    $data = setupRequestLetterData();
    createReportForLgu($data);

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertRedirect();

    Illuminate\Support\Facades\Notification::assertSentTo($data['admin'], App\Notifications\RequestLetterSubmittedNotification::class);
    Illuminate\Support\Facades\Notification::assertSentTo($data['provincial'], App\Notifications\RequestLetterSubmittedNotification::class);
    Illuminate\Support\Facades\Notification::assertSentTo($data['regional'], App\Notifications\RequestLetterSubmittedNotification::class);
    Illuminate\Support\Facades\Notification::assertNotSentTo($data['lguUser'], App\Notifications\RequestLetterSubmittedNotification::class);
});

test('lgu user from different region cannot upload request letter for massive incident', function () {
    Storage::fake('local');
    $region = Region::factory()->create();
    $otherRegion = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $otherRegion->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id]);
    $lguUser = User::factory()->lgu($lgu)->create();
    $admin = User::factory()->admin()->create(['region_id' => $region->id]);

    $incident = Incident::factory()->create(['created_by' => $admin->id, 'type' => 'massive']);

    Report::factory()->create([
        'user_id' => $lguUser->id,
        'incident_id' => $incident->id,
        'city_municipality_id' => $lgu->id,
        'status' => 'validated',
        'affected_areas' => [['barangay' => 'Brgy. Test', 'families' => 100, 'persons' => 400]],
    ]);

    $this->actingAs($lguUser)
        ->post("/incidents/{$incident->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertForbidden();
});

// --- Validation Tests ---

test('quantity exceeding affected families is rejected', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data, affectedFamilies: 50);

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 100],
            ],
        ])
        ->assertSessionHasErrors('augmentation_items.0.quantity');
});

test('non-pdf file is rejected', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data);

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.docx', 500, 'application/msword'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertSessionHasErrors('file');
});

test('duplicate augmentation types are rejected', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data);

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 10],
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 20],
            ],
        ])
        ->assertSessionHasErrors('augmentation_items.1.type');
});

// --- Download Tests ---

test('admin can download any request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data);

    Storage::disk('local')->put('request-letters/test.pdf', 'fake pdf content');
    $letter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
        'file_path' => 'request-letters/test.pdf',
    ]);

    $this->actingAs($data['admin'])
        ->get("/incidents/{$data['incident']->id}/request-letters/{$letter->id}")
        ->assertSuccessful();
});

test('provincial can download request letter from their province', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();

    Storage::disk('local')->put('request-letters/test.pdf', 'fake pdf content');
    $letter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
        'file_path' => 'request-letters/test.pdf',
    ]);

    $this->actingAs($data['provincial'])
        ->get("/incidents/{$data['incident']->id}/request-letters/{$letter->id}")
        ->assertSuccessful();
});

test('regional can download request letter from their region', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();

    Storage::disk('local')->put('request-letters/test.pdf', 'fake pdf content');
    $letter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
        'file_path' => 'request-letters/test.pdf',
    ]);

    $this->actingAs($data['regional'])
        ->get("/incidents/{$data['incident']->id}/request-letters/{$letter->id}")
        ->assertSuccessful();
});

test('lgu user from different city cannot download request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();

    $otherLgu = CityMunicipality::factory()->create(['province_id' => $data['province']->id]);
    $otherLguUser = User::factory()->lgu($otherLgu)->create();

    Storage::disk('local')->put('request-letters/test.pdf', 'fake pdf content');
    $letter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
        'file_path' => 'request-letters/test.pdf',
    ]);

    $this->actingAs($otherLguUser)
        ->get("/incidents/{$data['incident']->id}/request-letters/{$letter->id}")
        ->assertForbidden();
});

// --- Delete Tests ---

test('creator can delete their request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();

    Storage::disk('local')->put('request-letters/test.pdf', 'fake pdf content');
    $letter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
        'file_path' => 'request-letters/test.pdf',
    ]);

    $this->actingAs($data['lguUser'])
        ->delete("/incidents/{$data['incident']->id}/request-letters/{$letter->id}")
        ->assertRedirect();

    $this->assertDatabaseCount('request_letters', 0);
    Storage::disk('local')->assertMissing('request-letters/test.pdf');
});

test('other lgu user cannot delete request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();

    $otherLgu = CityMunicipality::factory()->create(['province_id' => $data['province']->id]);
    $otherLguUser = User::factory()->lgu($otherLgu)->create();

    $letter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
    ]);

    $this->actingAs($otherLguUser)
        ->delete("/incidents/{$data['incident']->id}/request-letters/{$letter->id}")
        ->assertForbidden();

    $this->assertDatabaseCount('request_letters', 1);
});

test('admin cannot delete request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();

    $letter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
    ]);

    $this->actingAs($data['admin'])
        ->delete("/incidents/{$data['incident']->id}/request-letters/{$letter->id}")
        ->assertForbidden();
});

test('creator cannot delete endorsed request letter', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();

    Storage::disk('local')->put('request-letters/test.pdf', 'fake pdf content');
    $letter = RequestLetter::factory()->create([
        'incident_id' => $data['incident']->id,
        'user_id' => $data['lguUser']->id,
        'city_municipality_id' => $data['lgu']->id,
        'file_path' => 'request-letters/test.pdf',
        'status' => 'endorsed',
    ]);

    $this->actingAs($data['lguUser'])
        ->delete("/incidents/{$data['incident']->id}/request-letters/{$letter->id}")
        ->assertForbidden();

    $this->assertDatabaseCount('request_letters', 1);
});

// --- Multiple Uploads ---

test('multiple uploads per incident are allowed', function () {
    Storage::fake('local');
    $data = setupRequestLetterData();
    createReportForLgu($data);

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request1.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::FamilyFoodPacks->value, 'quantity' => 50],
            ],
        ])
        ->assertRedirect();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/request-letters", [
            'file' => UploadedFile::fake()->create('request2.pdf', 500, 'application/pdf'),
            'augmentation_items' => [
                ['type' => AugmentationType::HygieneKits->value, 'quantity' => 30],
            ],
        ])
        ->assertRedirect();

    $this->assertDatabaseCount('request_letters', 2);
});
