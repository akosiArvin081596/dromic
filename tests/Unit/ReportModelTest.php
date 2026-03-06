<?php

use App\Models\Report;

test('report has correct casts', function () {
    $report = new Report;
    $casts = $report->getCasts();

    expect($casts)->toHaveKey('report_date', 'date')
        ->toHaveKey('affected_areas', 'array')
        ->toHaveKey('inside_evacuation_centers', 'array')
        ->toHaveKey('age_distribution', 'array')
        ->toHaveKey('vulnerable_sectors', 'array')
        ->toHaveKey('outside_evacuation_centers', 'array')
        ->toHaveKey('damaged_houses', 'array');
});

test('report has fillable attributes', function () {
    $report = new Report;
    $fillable = $report->getFillable();

    expect($fillable)->toContain('report_number')
        ->toContain('incident_id')
        ->toContain('report_type')
        ->toContain('sequence_number')
        ->toContain('city_municipality_id')
        ->toContain('affected_areas')
        ->toContain('status');
});
