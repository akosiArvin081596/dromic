<?php

use App\Enums\ReportType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportSequenceService;

beforeEach(function () {
    $this->service = new ReportSequenceService;

    $this->lgu = CityMunicipality::factory()->create(['psgc_code' => '1234567890']);
    $this->lguUser = User::factory()->lgu($this->lgu)->create();
    $this->incident = Incident::factory()->create(['created_by' => $this->lguUser->id]);
    $this->incident->cityMunicipalities()->attach($this->lgu->id);
});

test('first report returns initial type with sequence 0', function () {
    $info = $this->service->getNextReportInfo($this->incident->id, $this->lgu->id);

    expect($info['type'])->toBe(ReportType::Initial)
        ->and($info['sequence'])->toBe(0)
        ->and($info['previous_report_id'])->toBeNull();
});

test('after initial report returns progress with sequence 1', function () {
    $initial = Report::factory()->create([
        'user_id' => $this->lguUser->id,
        'incident_id' => $this->incident->id,
        'city_municipality_id' => $this->lgu->id,
        'report_type' => ReportType::Initial,
        'sequence_number' => 0,
    ]);

    $info = $this->service->getNextReportInfo($this->incident->id, $this->lgu->id);

    expect($info['type'])->toBe(ReportType::Progress)
        ->and($info['sequence'])->toBe(1)
        ->and($info['previous_report_id'])->toBe($initial->id);
});

test('after progress report returns next progress with incremented sequence', function () {
    Report::factory()->create([
        'user_id' => $this->lguUser->id,
        'incident_id' => $this->incident->id,
        'city_municipality_id' => $this->lgu->id,
        'report_type' => ReportType::Initial,
        'sequence_number' => 0,
    ]);

    $progress1 = Report::factory()->create([
        'user_id' => $this->lguUser->id,
        'incident_id' => $this->incident->id,
        'city_municipality_id' => $this->lgu->id,
        'report_type' => ReportType::Progress,
        'sequence_number' => 1,
    ]);

    $info = $this->service->getNextReportInfo($this->incident->id, $this->lgu->id);

    expect($info['type'])->toBe(ReportType::Progress)
        ->and($info['sequence'])->toBe(2)
        ->and($info['previous_report_id'])->toBe($progress1->id);
});

test('after terminal report throws runtime exception', function () {
    Report::factory()->create([
        'user_id' => $this->lguUser->id,
        'incident_id' => $this->incident->id,
        'city_municipality_id' => $this->lgu->id,
        'report_type' => ReportType::Terminal,
        'sequence_number' => 2,
    ]);

    $this->service->getNextReportInfo($this->incident->id, $this->lgu->id);
})->throws(RuntimeException::class, 'A terminal report has already been filed');

test('generateReportNumber formats initial report correctly', function () {
    $number = $this->service->generateReportNumber(
        $this->incident,
        $this->lgu,
        ReportType::Initial,
        0,
    );

    expect($number)->toBe("DROMIC-{$this->incident->id}-1234567890-IR");
});

test('generateReportNumber formats progress report correctly', function () {
    $number = $this->service->generateReportNumber(
        $this->incident,
        $this->lgu,
        ReportType::Progress,
        3,
    );

    expect($number)->toBe("DROMIC-{$this->incident->id}-1234567890-PR3");
});

test('generateReportNumber formats terminal report correctly', function () {
    $number = $this->service->generateReportNumber(
        $this->incident,
        $this->lgu,
        ReportType::Terminal,
        5,
    );

    expect($number)->toBe("DROMIC-{$this->incident->id}-1234567890-TR");
});

test('copyFromPrevious returns all seven data fields', function () {
    $report = Report::factory()->create([
        'user_id' => $this->lguUser->id,
        'incident_id' => $this->incident->id,
        'city_municipality_id' => $this->lgu->id,
        'situation_overview' => 'Test overview',
    ]);

    $data = $this->service->copyFromPrevious($report);

    expect($data)->toHaveKeys([
        'situation_overview',
        'affected_areas',
        'inside_evacuation_centers',
        'age_distribution',
        'vulnerable_sectors',
        'outside_evacuation_centers',
        'damaged_houses',
    ])
        ->and($data['situation_overview'])->toBe('Test overview');
});
