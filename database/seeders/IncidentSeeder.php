<?php

namespace Database\Seeders;

use App\Enums\IncidentType;
use App\Enums\ReportType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\Report;
use App\Models\User;
use App\Services\ReportSequenceService;
use Illuminate\Database\Seeder;

class IncidentSeeder extends Seeder
{
    /**
     * Cut-off periods using DROMIC convention: within the same date, PM (noon) comes first, AM (midnight) second.
     *
     * @var array<int, array{date: string, time: string}>
     */
    private array $cutoffs = [
        ['date' => '2026-02-20', 'time' => '12:00 PM'], // cut-off 1: noon Feb 20
        ['date' => '2026-02-20', 'time' => '12:00 AM'], // cut-off 2: midnight Feb 20
        ['date' => '2026-02-21', 'time' => '12:00 PM'], // cut-off 3: noon Feb 21
        ['date' => '2026-02-21', 'time' => '12:00 AM'], // cut-off 4: midnight Feb 21
        ['date' => '2026-02-22', 'time' => '12:00 PM'], // cut-off 5: noon Feb 22
    ];

    public function run(): void
    {
        $sequenceService = app(ReportSequenceService::class);

        $admin = User::where('role', 'admin')->first();
        $lguUsers = User::where('role', 'lgu')->get()->keyBy('city_municipality_id');

        if (! $admin || $lguUsers->count() < 3) {
            $this->command->warn('Need admin + 3 LGU users. Skipping incident seeding.');

            return;
        }

        $buenavista = CityMunicipality::where('name', 'Buenavista')->first();
        $butuan = CityMunicipality::where('name', 'City of Butuan')->first();
        $cabadbaran = CityMunicipality::where('name', 'City of Cabadbaran')->first();

        if (! $buenavista || ! $butuan || ! $cabadbaran) {
            $this->command->warn('Required LGUs not found. Run PsgcSeeder first.');

            return;
        }

        // --- Massive incident: Typhoon Aghon ---
        $incident = Incident::create([
            'name' => 'Typhoon Aghon',
            'type' => IncidentType::Massive,
            'created_by' => $admin->id,
            'description' => 'Super Typhoon Aghon made landfall affecting multiple municipalities in Agusan del Norte.',
            'status' => 'active',
        ]);

        $incident->cityMunicipalities()->attach([
            $buenavista->id,
            $butuan->id,
            $cabadbaran->id,
        ]);

        $lguReportSets = [
            $buenavista->id => $this->buenavistaReports(),
            $butuan->id => $this->butuanReports(),
            $cabadbaran->id => $this->cabadbaranReports(),
        ];

        foreach ($lguReportSets as $lguId => $reports) {
            $lgu = CityMunicipality::find($lguId);
            $user = $lguUsers->get($lguId);

            if (! $lgu || ! $user) {
                continue;
            }

            $this->seedLguReports($incident, $lgu, $user, $sequenceService, $reports);
        }

        // --- Local incident: Flooding (single draft report for first LGU) ---
        $firstLguUser = $lguUsers->get($buenavista->id);
        if ($firstLguUser) {
            $localIncident = Incident::create([
                'name' => 'Flooding - Heavy Rainfall',
                'type' => IncidentType::Local,
                'created_by' => $firstLguUser->id,
                'description' => 'Localized flooding due to continuous heavy rainfall.',
                'status' => 'active',
            ]);

            $localIncident->cityMunicipalities()->attach([$buenavista->id]);

            $reportNumber = $sequenceService->generateReportNumber($localIncident, $buenavista, ReportType::Initial, 0);
            Report::factory()->create([
                'user_id' => $firstLguUser->id,
                'incident_id' => $localIncident->id,
                'report_number' => $reportNumber,
                'report_type' => ReportType::Initial,
                'sequence_number' => 0,
                'previous_report_id' => null,
                'city_municipality_id' => $buenavista->id,
                'status' => 'draft',
            ]);
        }

        $this->command->info('Created '.Incident::count().' incidents with '.Report::count().' reports.');
    }

    /**
     * @param array<int, array<string, mixed>> $reports
     */
    private function seedLguReports(
        Incident $incident,
        CityMunicipality $lgu,
        User $user,
        ReportSequenceService $sequenceService,
        array $reports,
    ): void {
        $previousReportId = null;

        foreach ($reports as $index => $data) {
            $type = $index === 0 ? ReportType::Initial : ReportType::Progress;
            $sequence = $index === 0 ? 0 : $index;
            $cutoff = $this->cutoffs[$data['cutoff']];
            $reportNumber = $sequenceService->generateReportNumber($incident, $lgu, $type, $sequence);

            $report = Report::create([
                'user_id' => $user->id,
                'incident_id' => $incident->id,
                'report_number' => $reportNumber,
                'report_type' => $type,
                'sequence_number' => $sequence,
                'previous_report_id' => $previousReportId,
                'city_municipality_id' => $lgu->id,
                'report_date' => $cutoff['date'],
                'report_time' => $cutoff['time'],
                'situation_overview' => $data['situation_overview'],
                'affected_areas' => $data['affected_areas'],
                'inside_evacuation_centers' => $data['inside_evacuation_centers'],
                'age_distribution' => $data['age_distribution'],
                'vulnerable_sectors' => $data['vulnerable_sectors'],
                'outside_evacuation_centers' => $data['outside_evacuation_centers'],
                'non_idps' => $data['non_idps'],
                'damaged_houses' => $data['damaged_houses'],
                'status' => $data['status'],
            ]);

            $previousReportId = $report->id;
        }
    }

    // ──────────────────────────────────────────────────────────
    //  Buenavista — cutoffs 0, 2, 3, 4 (skips cutoff 1)
    // ──────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function buenavistaReports(): array
    {
        return [
            // IR — cutoff 0 (Feb 20 12:00 PM)
            [
                'cutoff' => 0,
                'status' => 'validated',
                'situation_overview' => 'Typhoon Aghon made landfall in Agusan del Norte bringing heavy rains and strong winds. Flooding reported in low-lying barangays of Buenavista. Preemptive evacuations conducted in Abilan and Agong-ong.',
                'affected_areas' => [
                    ['barangay' => 'Abilan', 'families' => 35, 'persons' => 151],
                    ['barangay' => 'Agong-ong', 'families' => 42, 'persons' => 181],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Abilan', 'ec_name' => 'Abilan Elementary School', 'families_cum' => 25, 'families_now' => 25, 'persons_cum' => 110, 'persons_now' => 110, 'origin' => 'Abilan', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Agong-ong', 'families_cum' => 20, 'families_now' => 20, 'persons_cum' => 84, 'persons_now' => 84, 'origin' => 'Agong-ong'],
                ],
                'non_idps' => [
                    ['barangay' => 'Alubijid', 'families_cum' => 15, 'families_now' => 15, 'persons_cum' => 64, 'persons_now' => 64],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Abilan', 'totally_damaged' => 2, 'partially_damaged' => 5, 'estimated_cost' => 250000],
                ],
                'age_distribution' => $this->ageDistribution(91, 91, 103, 103),
                'vulnerable_sectors' => $this->vulnerableSectors(14, 14, 15, 15),
            ],

            // PR1 — cutoff 2 (Feb 21 12:00 PM) — skipped cutoff 1 (carry-forward)
            [
                'cutoff' => 2,
                'status' => 'validated',
                'situation_overview' => 'Floodwaters receding in Abilan but Guinabsan now reporting displaced families. Additional damage assessments ongoing. Some evacuees from Abilan have returned home.',
                'affected_areas' => [
                    ['barangay' => 'Abilan', 'families' => 35, 'persons' => 151],
                    ['barangay' => 'Agong-ong', 'families' => 42, 'persons' => 181],
                    ['barangay' => 'Guinabsan', 'families' => 28, 'persons' => 120],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Abilan', 'ec_name' => 'Abilan Elementary School', 'families_cum' => 30, 'families_now' => 20, 'persons_cum' => 132, 'persons_now' => 88, 'origin' => 'Abilan', 'remarks' => '5 families returned home'],
                    ['barangay' => 'Guinabsan', 'ec_name' => 'Guinabsan Community Center', 'families_cum' => 18, 'families_now' => 18, 'persons_cum' => 76, 'persons_now' => 76, 'origin' => 'Guinabsan', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Agong-ong', 'families_cum' => 28, 'families_now' => 22, 'persons_cum' => 118, 'persons_now' => 93, 'origin' => 'Agong-ong'],
                ],
                'non_idps' => [
                    ['barangay' => 'Alubijid', 'families_cum' => 22, 'families_now' => 18, 'persons_cum' => 93, 'persons_now' => 76],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Abilan', 'totally_damaged' => 4, 'partially_damaged' => 10, 'estimated_cost' => 520000],
                    ['barangay' => 'Agong-ong', 'totally_damaged' => 1, 'partially_damaged' => 3, 'estimated_cost' => 150000],
                ],
                'age_distribution' => $this->ageDistribution(153, 121, 173, 136),
                'vulnerable_sectors' => $this->vulnerableSectors(23, 18, 26, 20),
            ],

            // PR2 — cutoff 3 (Feb 21 12:00 AM)
            [
                'cutoff' => 3,
                'status' => 'for_validation',
                'situation_overview' => 'Situation stabilizing. Majority of evacuees from Abilan EC have returned. Guinabsan EC still operational. Damage assessment near completion.',
                'affected_areas' => [
                    ['barangay' => 'Abilan', 'families' => 35, 'persons' => 151],
                    ['barangay' => 'Agong-ong', 'families' => 42, 'persons' => 181],
                    ['barangay' => 'Guinabsan', 'families' => 28, 'persons' => 120],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Abilan', 'ec_name' => 'Abilan Elementary School', 'families_cum' => 30, 'families_now' => 12, 'persons_cum' => 132, 'persons_now' => 53, 'origin' => 'Abilan', 'remarks' => 'Most families returned'],
                    ['barangay' => 'Guinabsan', 'ec_name' => 'Guinabsan Community Center', 'families_cum' => 20, 'families_now' => 15, 'persons_cum' => 84, 'persons_now' => 63, 'origin' => 'Guinabsan', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Agong-ong', 'families_cum' => 28, 'families_now' => 15, 'persons_cum' => 118, 'persons_now' => 63, 'origin' => 'Agong-ong'],
                ],
                'non_idps' => [
                    ['barangay' => 'Alubijid', 'families_cum' => 22, 'families_now' => 14, 'persons_cum' => 93, 'persons_now' => 59],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Abilan', 'totally_damaged' => 5, 'partially_damaged' => 12, 'estimated_cost' => 650000],
                    ['barangay' => 'Agong-ong', 'totally_damaged' => 2, 'partially_damaged' => 5, 'estimated_cost' => 250000],
                ],
                'age_distribution' => $this->ageDistribution(157, 84, 177, 95),
                'vulnerable_sectors' => $this->vulnerableSectors(24, 13, 27, 14),
            ],

            // PR3 — cutoff 4 (Feb 22 12:00 PM)
            [
                'cutoff' => 4,
                'status' => 'for_validation',
                'situation_overview' => 'Recovery phase. Few families remain in evacuation centers. Clean-up operations ongoing. Final damage assessment submitted to municipal DRRMO.',
                'affected_areas' => [
                    ['barangay' => 'Abilan', 'families' => 35, 'persons' => 151],
                    ['barangay' => 'Agong-ong', 'families' => 42, 'persons' => 181],
                    ['barangay' => 'Guinabsan', 'families' => 28, 'persons' => 120],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Abilan', 'ec_name' => 'Abilan Elementary School', 'families_cum' => 30, 'families_now' => 5, 'persons_cum' => 132, 'persons_now' => 22, 'origin' => 'Abilan', 'remarks' => 'Few families remain in EC'],
                    ['barangay' => 'Guinabsan', 'ec_name' => 'Guinabsan Community Center', 'families_cum' => 20, 'families_now' => 8, 'persons_cum' => 84, 'persons_now' => 34, 'origin' => 'Guinabsan', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Agong-ong', 'families_cum' => 28, 'families_now' => 8, 'persons_cum' => 118, 'persons_now' => 34, 'origin' => 'Agong-ong'],
                ],
                'non_idps' => [
                    ['barangay' => 'Alubijid', 'families_cum' => 22, 'families_now' => 8, 'persons_cum' => 93, 'persons_now' => 34],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Abilan', 'totally_damaged' => 5, 'partially_damaged' => 14, 'estimated_cost' => 750000],
                    ['barangay' => 'Agong-ong', 'totally_damaged' => 2, 'partially_damaged' => 6, 'estimated_cost' => 300000],
                ],
                'age_distribution' => $this->ageDistribution(157, 42, 177, 48),
                'vulnerable_sectors' => $this->vulnerableSectors(24, 6, 27, 7),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────
    //  City of Butuan — cutoffs 0, 1, 2, 4 (skips cutoff 3)
    // ──────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function butuanReports(): array
    {
        return [
            // IR — cutoff 0 (Feb 20 12:00 PM)
            [
                'cutoff' => 0,
                'status' => 'validated',
                'situation_overview' => 'Typhoon Aghon caused widespread flooding in Butuan City. Agusan River overflow affecting riverside barangays. Preemptive and forced evacuations underway in Amparo, Ampayon, and Baan Riverside.',
                'affected_areas' => [
                    ['barangay' => 'Amparo', 'families' => 72, 'persons' => 310],
                    ['barangay' => 'Ampayon', 'families' => 85, 'persons' => 366],
                    ['barangay' => 'Baan Riverside', 'families' => 55, 'persons' => 237],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Amparo', 'ec_name' => 'Amparo Central Elementary School', 'families_cum' => 55, 'families_now' => 55, 'persons_cum' => 237, 'persons_now' => 237, 'origin' => 'Amparo', 'remarks' => ''],
                    ['barangay' => 'Ampayon', 'ec_name' => 'Ampayon National High School', 'families_cum' => 70, 'families_now' => 70, 'persons_cum' => 301, 'persons_now' => 301, 'origin' => 'Ampayon', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Baan Riverside', 'families_cum' => 45, 'families_now' => 45, 'persons_cum' => 194, 'persons_now' => 194, 'origin' => 'Baan Riverside'],
                ],
                'non_idps' => [
                    ['barangay' => 'Libertad', 'families_cum' => 30, 'families_now' => 30, 'persons_cum' => 129, 'persons_now' => 129],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Amparo', 'totally_damaged' => 3, 'partially_damaged' => 8, 'estimated_cost' => 450000],
                ],
                'age_distribution' => $this->ageDistribution(344, 344, 388, 388),
                'vulnerable_sectors' => $this->vulnerableSectors(41, 41, 47, 47),
            ],

            // PR1 — cutoff 1 (Feb 20 12:00 AM)
            [
                'cutoff' => 1,
                'status' => 'validated',
                'situation_overview' => 'Floodwaters remain high along Agusan River. Libertad now reporting affected families. Additional evacuees arriving at Amparo and Ampayon ECs. Some early evacuees have returned as waters recede in higher areas.',
                'affected_areas' => [
                    ['barangay' => 'Amparo', 'families' => 72, 'persons' => 310],
                    ['barangay' => 'Ampayon', 'families' => 85, 'persons' => 366],
                    ['barangay' => 'Baan Riverside', 'families' => 55, 'persons' => 237],
                    ['barangay' => 'Libertad', 'families' => 48, 'persons' => 207],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Amparo', 'ec_name' => 'Amparo Central Elementary School', 'families_cum' => 75, 'families_now' => 50, 'persons_cum' => 323, 'persons_now' => 215, 'origin' => 'Amparo', 'remarks' => 'Some families returned home'],
                    ['barangay' => 'Ampayon', 'ec_name' => 'Ampayon National High School', 'families_cum' => 90, 'families_now' => 65, 'persons_cum' => 387, 'persons_now' => 280, 'origin' => 'Ampayon', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Baan Riverside', 'families_cum' => 60, 'families_now' => 40, 'persons_cum' => 258, 'persons_now' => 172, 'origin' => 'Baan Riverside'],
                ],
                'non_idps' => [
                    ['barangay' => 'Libertad', 'families_cum' => 45, 'families_now' => 40, 'persons_cum' => 194, 'persons_now' => 172],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Amparo', 'totally_damaged' => 5, 'partially_damaged' => 12, 'estimated_cost' => 720000],
                    ['barangay' => 'Ampayon', 'totally_damaged' => 2, 'partially_damaged' => 7, 'estimated_cost' => 380000],
                ],
                'age_distribution' => $this->ageDistribution(455, 313, 513, 354),
                'vulnerable_sectors' => $this->vulnerableSectors(55, 38, 62, 42),
            ],

            // PR2 — cutoff 2 (Feb 21 12:00 PM)
            [
                'cutoff' => 2,
                'status' => 'validated',
                'situation_overview' => 'Flood waters gradually receding. Bading now included in affected areas after post-flood assessment. Majority of Amparo evacuees have returned. Damage assessment teams deployed across all affected barangays.',
                'affected_areas' => [
                    ['barangay' => 'Amparo', 'families' => 72, 'persons' => 310],
                    ['barangay' => 'Ampayon', 'families' => 85, 'persons' => 366],
                    ['barangay' => 'Baan Riverside', 'families' => 55, 'persons' => 237],
                    ['barangay' => 'Libertad', 'families' => 48, 'persons' => 207],
                    ['barangay' => 'Bading', 'families' => 38, 'persons' => 164],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Amparo', 'ec_name' => 'Amparo Central Elementary School', 'families_cum' => 85, 'families_now' => 35, 'persons_cum' => 366, 'persons_now' => 151, 'origin' => 'Amparo', 'remarks' => 'Majority returned'],
                    ['barangay' => 'Ampayon', 'ec_name' => 'Ampayon National High School', 'families_cum' => 100, 'families_now' => 45, 'persons_cum' => 430, 'persons_now' => 194, 'origin' => 'Ampayon', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Baan Riverside', 'families_cum' => 72, 'families_now' => 28, 'persons_cum' => 310, 'persons_now' => 120, 'origin' => 'Baan Riverside'],
                ],
                'non_idps' => [
                    ['barangay' => 'Libertad', 'families_cum' => 55, 'families_now' => 38, 'persons_cum' => 237, 'persons_now' => 164],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Amparo', 'totally_damaged' => 8, 'partially_damaged' => 18, 'estimated_cost' => 1100000],
                    ['barangay' => 'Ampayon', 'totally_damaged' => 4, 'partially_damaged' => 12, 'estimated_cost' => 680000],
                    ['barangay' => 'Baan Riverside', 'totally_damaged' => 2, 'partially_damaged' => 6, 'estimated_cost' => 320000],
                ],
                'age_distribution' => $this->ageDistribution(520, 219, 586, 246),
                'vulnerable_sectors' => $this->vulnerableSectors(62, 26, 70, 30),
            ],

            // PR3 — cutoff 4 (Feb 22 12:00 PM) — skipped cutoff 3 (carry-forward)
            [
                'cutoff' => 4,
                'status' => 'for_validation',
                'situation_overview' => 'Recovery operations underway. Evacuation centers winding down. Clean-up and debris clearing in progress. DSWD assistance distribution scheduled. Final damage figures being consolidated.',
                'affected_areas' => [
                    ['barangay' => 'Amparo', 'families' => 72, 'persons' => 310],
                    ['barangay' => 'Ampayon', 'families' => 85, 'persons' => 366],
                    ['barangay' => 'Baan Riverside', 'families' => 55, 'persons' => 237],
                    ['barangay' => 'Libertad', 'families' => 48, 'persons' => 207],
                    ['barangay' => 'Bading', 'families' => 38, 'persons' => 164],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Amparo', 'ec_name' => 'Amparo Central Elementary School', 'families_cum' => 85, 'families_now' => 15, 'persons_cum' => 366, 'persons_now' => 65, 'origin' => 'Amparo', 'remarks' => 'Winding down operations'],
                    ['barangay' => 'Ampayon', 'ec_name' => 'Ampayon National High School', 'families_cum' => 100, 'families_now' => 20, 'persons_cum' => 430, 'persons_now' => 86, 'origin' => 'Ampayon', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Baan Riverside', 'families_cum' => 72, 'families_now' => 12, 'persons_cum' => 310, 'persons_now' => 52, 'origin' => 'Baan Riverside'],
                ],
                'non_idps' => [
                    ['barangay' => 'Libertad', 'families_cum' => 55, 'families_now' => 22, 'persons_cum' => 237, 'persons_now' => 95],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Amparo', 'totally_damaged' => 10, 'partially_damaged' => 22, 'estimated_cost' => 1350000],
                    ['barangay' => 'Ampayon', 'totally_damaged' => 5, 'partially_damaged' => 15, 'estimated_cost' => 850000],
                    ['barangay' => 'Baan Riverside', 'totally_damaged' => 3, 'partially_damaged' => 8, 'estimated_cost' => 450000],
                ],
                'age_distribution' => $this->ageDistribution(520, 95, 586, 108),
                'vulnerable_sectors' => $this->vulnerableSectors(62, 11, 70, 13),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────
    //  City of Cabadbaran — cutoffs 1, 3, 4 (skips cutoffs 0, 2)
    // ──────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    private function cabadbaranReports(): array
    {
        return [
            // IR — cutoff 1 (Feb 20 12:00 AM) — late start, no report at cutoff 0
            [
                'cutoff' => 1,
                'status' => 'validated',
                'situation_overview' => 'Typhoon Aghon brought heavy rainfall to Cabadbaran. Bay-ang and Comagascas experiencing flooding. Evacuation operations initiated at Bay-ang Elementary School.',
                'affected_areas' => [
                    ['barangay' => 'Bay-ang', 'families' => 52, 'persons' => 224],
                    ['barangay' => 'Comagascas', 'families' => 38, 'persons' => 164],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Bay-ang', 'ec_name' => 'Bay-ang Elementary School', 'families_cum' => 40, 'families_now' => 40, 'persons_cum' => 172, 'persons_now' => 172, 'origin' => 'Bay-ang', 'remarks' => ''],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Comagascas', 'families_cum' => 30, 'families_now' => 30, 'persons_cum' => 129, 'persons_now' => 129, 'origin' => 'Comagascas'],
                ],
                'non_idps' => [
                    ['barangay' => 'Calamba', 'families_cum' => 20, 'families_now' => 20, 'persons_cum' => 86, 'persons_now' => 86],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Bay-ang', 'totally_damaged' => 1, 'partially_damaged' => 4, 'estimated_cost' => 180000],
                ],
                'age_distribution' => $this->ageDistribution(142, 142, 159, 159),
                'vulnerable_sectors' => $this->vulnerableSectors(21, 21, 24, 24),
            ],

            // PR1 — cutoff 3 (Feb 21 12:00 AM) — skipped cutoff 2 (carry-forward)
            [
                'cutoff' => 3,
                'status' => 'for_validation',
                'situation_overview' => 'Del Pilar now reporting affected families. Some evacuees from Bay-ang EC have returned home. Damage assessment teams surveying affected areas. Relief goods distributed to evacuation center.',
                'affected_areas' => [
                    ['barangay' => 'Bay-ang', 'families' => 52, 'persons' => 224],
                    ['barangay' => 'Comagascas', 'families' => 38, 'persons' => 164],
                    ['barangay' => 'Del Pilar', 'families' => 30, 'persons' => 129],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Bay-ang', 'ec_name' => 'Bay-ang Elementary School', 'families_cum' => 52, 'families_now' => 30, 'persons_cum' => 224, 'persons_now' => 129, 'origin' => 'Bay-ang', 'remarks' => 'Some families returned home'],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Comagascas', 'families_cum' => 38, 'families_now' => 22, 'persons_cum' => 164, 'persons_now' => 95, 'origin' => 'Comagascas'],
                ],
                'non_idps' => [
                    ['barangay' => 'Calamba', 'families_cum' => 28, 'families_now' => 22, 'persons_cum' => 120, 'persons_now' => 95],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Bay-ang', 'totally_damaged' => 3, 'partially_damaged' => 8, 'estimated_cost' => 420000],
                    ['barangay' => 'Del Pilar', 'totally_damaged' => 1, 'partially_damaged' => 3, 'estimated_cost' => 150000],
                ],
                'age_distribution' => $this->ageDistribution(182, 105, 206, 119),
                'vulnerable_sectors' => $this->vulnerableSectors(27, 16, 31, 18),
            ],

            // PR2 — cutoff 4 (Feb 22 12:00 PM)
            [
                'cutoff' => 4,
                'status' => 'for_validation',
                'situation_overview' => 'Situation improving. Few families remain at Bay-ang EC. Waters have fully receded in Comagascas. Damage assessment finalized. DSWD assistance being processed.',
                'affected_areas' => [
                    ['barangay' => 'Bay-ang', 'families' => 52, 'persons' => 224],
                    ['barangay' => 'Comagascas', 'families' => 38, 'persons' => 164],
                    ['barangay' => 'Del Pilar', 'families' => 30, 'persons' => 129],
                ],
                'inside_evacuation_centers' => [
                    ['barangay' => 'Bay-ang', 'ec_name' => 'Bay-ang Elementary School', 'families_cum' => 52, 'families_now' => 15, 'persons_cum' => 224, 'persons_now' => 65, 'origin' => 'Bay-ang', 'remarks' => 'Few families remain'],
                ],
                'outside_evacuation_centers' => [
                    ['barangay' => 'Comagascas', 'families_cum' => 38, 'families_now' => 10, 'persons_cum' => 164, 'persons_now' => 43, 'origin' => 'Comagascas'],
                ],
                'non_idps' => [
                    ['barangay' => 'Calamba', 'families_cum' => 28, 'families_now' => 12, 'persons_cum' => 120, 'persons_now' => 52],
                ],
                'damaged_houses' => [
                    ['barangay' => 'Bay-ang', 'totally_damaged' => 4, 'partially_damaged' => 10, 'estimated_cost' => 540000],
                    ['barangay' => 'Del Pilar', 'totally_damaged' => 2, 'partially_damaged' => 5, 'estimated_cost' => 250000],
                ],
                'age_distribution' => $this->ageDistribution(182, 51, 206, 57),
                'vulnerable_sectors' => $this->vulnerableSectors(27, 8, 31, 9),
            ],
        ];
    }

    // ──────────────────────────────────────────────────────────
    //  Helpers — distribute totals across age groups / sectors
    // ──────────────────────────────────────────────────────────

    /**
     * Distribute totals across 7 age groups using Philippine demographic ratios.
     *
     * @return array<string, array{male_cum: int, male_now: int, female_cum: int, female_now: int}>
     */
    private function ageDistribution(int $maleCum, int $maleNow, int $femaleCum, int $femaleNow): array
    {
        $groups = ['0-5', '6-12', '13-17', '18-35', '36-59', '60-69', '70+'];
        $pcts = [0.12, 0.15, 0.10, 0.28, 0.22, 0.08, 0.05];

        $result = [];
        $mcRem = $maleCum;
        $mnRem = $maleNow;
        $fcRem = $femaleCum;
        $fnRem = $femaleNow;

        foreach ($groups as $i => $group) {
            $last = $i === count($groups) - 1;
            $result[$group] = [
                'male_cum' => $last ? $mcRem : (int) round($maleCum * $pcts[$i]),
                'male_now' => $last ? $mnRem : (int) round($maleNow * $pcts[$i]),
                'female_cum' => $last ? $fcRem : (int) round($femaleCum * $pcts[$i]),
                'female_now' => $last ? $fnRem : (int) round($femaleNow * $pcts[$i]),
            ];

            if (! $last) {
                $mcRem -= $result[$group]['male_cum'];
                $mnRem -= $result[$group]['male_now'];
                $fcRem -= $result[$group]['female_cum'];
                $fnRem -= $result[$group]['female_now'];
            }
        }

        return $result;
    }

    /**
     * Distribute totals across 5 vulnerable sectors.
     *
     * @return array<string, array{male_cum: int, male_now: int, female_cum: int, female_now: int}>
     */
    private function vulnerableSectors(int $maleCum, int $maleNow, int $femaleCum, int $femaleNow): array
    {
        $sectors = [
            'Pregnant/Lactating' => [0.00, 0.35],
            'Solo Parent' => [0.20, 0.20],
            'PWD' => [0.15, 0.10],
            'Indigenous People' => [0.30, 0.15],
            'Senior Citizen' => [0.35, 0.20],
        ];

        $result = [];

        foreach ($sectors as $name => [$malePct, $femalePct]) {
            $result[$name] = [
                'male_cum' => (int) round($maleCum * $malePct),
                'male_now' => (int) round($maleNow * $malePct),
                'female_cum' => (int) round($femaleCum * $femalePct),
                'female_now' => (int) round($femaleNow * $femalePct),
            ];
        }

        return $result;
    }
}
