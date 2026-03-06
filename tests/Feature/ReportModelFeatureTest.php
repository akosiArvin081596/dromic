<?php

use App\Models\CityMunicipality;
use App\Models\Report;
use App\Models\User;

test('report belongs to user', function () {
    $report = Report::factory()->create();

    expect($report->user)->toBeInstanceOf(User::class);
});

test('report belongs to city municipality', function () {
    $report = Report::factory()->create();

    expect($report->cityMunicipality)->toBeInstanceOf(CityMunicipality::class);
});

test('report scopes by status', function () {
    Report::factory()->create(['status' => 'draft']);
    Report::factory()->create(['status' => 'for_validation']);
    Report::factory()->create(['status' => 'validated']);

    expect(Report::query()->byStatus('draft')->count())->toBe(1)
        ->and(Report::query()->byStatus('for_validation')->count())->toBe(1)
        ->and(Report::query()->byStatus('validated')->count())->toBe(1);
});

test('report scopes by city municipality', function () {
    $cm = CityMunicipality::factory()->create();
    Report::factory()->count(2)->create(['city_municipality_id' => $cm->id]);
    Report::factory()->create();

    expect(Report::query()->forCityMunicipality($cm->id)->count())->toBe(2);
});
