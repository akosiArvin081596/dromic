<?php

use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Province;
use App\Models\Region;
use App\Models\Report;
use App\Models\User;
use App\Notifications\IncidentCreatedNotification;
use App\Notifications\ReportReturnedNotification;
use App\Notifications\ReportSubmittedNotification;
use App\Notifications\ReportValidatedNotification;
use Illuminate\Support\Facades\Notification;

function setupNotificationData(): array
{
    $region = Region::factory()->create();
    $province = Province::factory()->create(['region_id' => $region->id]);
    $lgu = CityMunicipality::factory()->create(['province_id' => $province->id, 'psgc_code' => '9876543210']);

    $admin = User::factory()->admin()->create();
    $regional = User::factory()->regional($region)->create();
    $provincial = User::factory()->provincial($province)->create();
    $lguUser = User::factory()->lgu($lgu)->create();

    $incident = Incident::factory()->create(['created_by' => $admin->id]);
    $incident->cityMunicipalities()->attach($lgu->id);

    return compact('admin', 'regional', 'provincial', 'lguUser', 'region', 'province', 'lgu', 'incident');
}

function validReportPayload(string $status = 'for_validation'): array
{
    return [
        'report_date' => '2026-01-15',
        'report_time' => '6:00 AM',
        'situation_overview' => 'Test situation overview.',
        'status' => $status,
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

// --- Incident DB notification tests ---

test('creating an incident sends database notifications to recipients', function () {
    Notification::fake();

    $data = setupNotificationData();

    $this->actingAs($data['admin'])
        ->post('/incidents', [
            'category' => 'tropical_cyclone',
            'identifier' => 'Notification',
            'type' => 'massive',
            'description' => 'Testing notifications.',
            'city_municipality_ids' => [$data['lgu']->id],
        ])
        ->assertRedirect();

    Notification::assertSentTo($data['provincial'], IncidentCreatedNotification::class);
    Notification::assertSentTo($data['lguUser'], IncidentCreatedNotification::class);
    Notification::assertNotSentTo($data['admin'], IncidentCreatedNotification::class);
    Notification::assertNotSentTo($data['regional'], IncidentCreatedNotification::class);
});

test('incident notification stores correct data structure', function () {
    Notification::fake();

    $data = setupNotificationData();

    $this->actingAs($data['admin'])
        ->post('/incidents', [
            'category' => 'tropical_cyclone',
            'identifier' => 'Payload Test',
            'type' => 'local',
            'city_municipality_ids' => [$data['lgu']->id],
        ])
        ->assertRedirect();

    Notification::assertSentTo($data['lguUser'], IncidentCreatedNotification::class, function ($notification) {
        $dbData = $notification->toDatabase($notification);

        return $dbData['kind'] === 'incident'
            && isset($dbData['incident']['id'])
            && $dbData['incident']['name'] === 'Tropical Cyclone Payload Test';
    });
});

// --- Report submitted DB notification tests ---

test('submitting a report for validation sends database notifications', function () {
    Notification::fake();

    $data = setupNotificationData();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", validReportPayload())
        ->assertRedirect();

    Notification::assertSentTo($data['admin'], ReportSubmittedNotification::class);
    Notification::assertSentTo($data['provincial'], ReportSubmittedNotification::class);
    Notification::assertNotSentTo($data['regional'], ReportSubmittedNotification::class);
    Notification::assertNotSentTo($data['lguUser'], ReportSubmittedNotification::class);
});

test('saving a report as draft does not send database notifications', function () {
    Notification::fake();

    $data = setupNotificationData();

    $this->actingAs($data['lguUser'])
        ->post("/incidents/{$data['incident']->id}/reports", validReportPayload('draft'))
        ->assertRedirect();

    Notification::assertNothingSent();
});

// --- Report validated DB notification tests ---

test('validating a report sends database notifications', function () {
    Notification::fake();

    $data = setupNotificationData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/validate")
        ->assertRedirect();

    Notification::assertSentTo($data['lguUser'], ReportValidatedNotification::class);
    Notification::assertSentTo($data['admin'], ReportValidatedNotification::class);
    Notification::assertNotSentTo($data['provincial'], ReportValidatedNotification::class);
});

// --- Report returned DB notification tests ---

test('returning a report sends database notification to LGU creator', function () {
    Notification::fake();

    $data = setupNotificationData();

    $report = Report::factory()->create([
        'user_id' => $data['lguUser']->id,
        'incident_id' => $data['incident']->id,
        'city_municipality_id' => $data['lgu']->id,
        'status' => 'for_validation',
    ]);

    $this->actingAs($data['provincial'])
        ->post("/incidents/{$data['incident']->id}/reports/{$report->id}/return", [
            'return_reason' => 'Incomplete data',
        ])
        ->assertRedirect();

    Notification::assertSentTo($data['lguUser'], ReportReturnedNotification::class);
    Notification::assertNotSentTo($data['provincial'], ReportReturnedNotification::class);
});

// --- NotificationController endpoint tests ---

test('authenticated user can fetch their notifications', function () {
    $data = setupNotificationData();

    $data['lguUser']->notify(new IncidentCreatedNotification([
        'id' => 1,
        'name' => 'Test Incident',
        'message' => 'Test message',
    ]));

    $this->actingAs($data['lguUser'])
        ->get('/notifications')
        ->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

test('unauthenticated user cannot fetch notifications', function () {
    $this->get('/notifications')->assertRedirect('/login');
});

test('mark single notification as read', function () {
    $data = setupNotificationData();

    $data['lguUser']->notify(new IncidentCreatedNotification([
        'id' => 1,
        'name' => 'Test',
        'message' => 'Test',
    ]));

    $notification = $data['lguUser']->notifications()->first();
    expect($notification->read_at)->toBeNull();

    $this->actingAs($data['lguUser'])
        ->patch("/notifications/{$notification->id}/read")
        ->assertSuccessful();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('mark all notifications as read', function () {
    $data = setupNotificationData();

    $data['lguUser']->notify(new IncidentCreatedNotification(['id' => 1, 'name' => 'Test 1', 'message' => 'Msg']));
    $data['lguUser']->notify(new IncidentCreatedNotification(['id' => 2, 'name' => 'Test 2', 'message' => 'Msg']));

    expect($data['lguUser']->unreadNotifications()->count())->toBe(2);

    $this->actingAs($data['lguUser'])
        ->post('/notifications/mark-all-read')
        ->assertSuccessful();

    expect($data['lguUser']->unreadNotifications()->count())->toBe(0);
});

test('user cannot mark another users notification as read', function () {
    $data = setupNotificationData();

    $data['lguUser']->notify(new IncidentCreatedNotification(['id' => 1, 'name' => 'Test', 'message' => 'Msg']));

    $notification = $data['lguUser']->notifications()->first();

    $this->actingAs($data['admin'])
        ->patch("/notifications/{$notification->id}/read")
        ->assertNotFound();
});

// --- Shared Inertia data test ---

test('unread notification count is shared via Inertia', function () {
    $data = setupNotificationData();

    $data['lguUser']->notify(new IncidentCreatedNotification(['id' => 1, 'name' => 'Test', 'message' => 'Msg']));
    $data['lguUser']->notify(new IncidentCreatedNotification(['id' => 2, 'name' => 'Test 2', 'message' => 'Msg']));

    $this->actingAs($data['lguUser'])
        ->get('/dashboard')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page->where('unreadNotificationCount', 2));
});
