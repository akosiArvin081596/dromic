<?php

namespace Database\Factories;

use App\Enums\ReportType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Report>
 */
class ReportFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $cumFamilies = fake()->numberBetween(50, 500);
        $nowFamilies = fake()->numberBetween(10, $cumFamilies);
        $cumPersons = $cumFamilies * fake()->numberBetween(3, 5);
        $nowPersons = $nowFamilies * fake()->numberBetween(3, 5);

        return [
            'user_id' => User::factory(),
            'incident_id' => Incident::factory(),
            'report_number' => 'DROMIC-'.fake()->unique()->numerify('####-####'),
            'report_type' => ReportType::Initial,
            'sequence_number' => 0,
            'previous_report_id' => null,
            'city_municipality_id' => CityMunicipality::factory(),
            'report_date' => fake()->dateTimeBetween('-30 days', 'now'),
            'report_time' => fake()->randomElement(['12:00 PM', '12:00 AM']),
            'situation_overview' => fake()->paragraph(3),
            'affected_areas' => [
                $this->makeAffectedArea(),
                $this->makeAffectedArea(),
            ],
            'inside_evacuation_centers' => [
                $this->makeInsideEC($cumFamilies, $nowFamilies, $cumPersons, $nowPersons),
            ],
            'age_distribution' => $this->makeAgeDistribution($nowPersons),
            'vulnerable_sectors' => $this->makeVulnerableSectors(),
            'outside_evacuation_centers' => [
                $this->makeOutsideEC(),
            ],
            'non_idps' => [
                $this->makeNonIdp(),
            ],
            'damaged_houses' => [
                $this->makeDamagedHouse(),
            ],
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
            'status' => fake()->randomElement(['draft', 'for_validation', 'validated']),
            'return_reason' => null,
        ];
    }

    public function returned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'returned',
            'return_reason' => fake()->sentence(),
        ]);
    }

    /** @return array<string, mixed> */
    private function makeAffectedArea(): array
    {
        $families = fake()->numberBetween(10, 200);

        return [
            'barangay' => 'Brgy. '.fake()->lastName(),
            'families' => $families,
            'persons' => $families * fake()->numberBetween(3, 5),
        ];
    }

    /** @return array<string, mixed> */
    private function makeInsideEC(int $cumFamilies, int $nowFamilies, int $cumPersons, int $nowPersons): array
    {
        return [
            'barangay' => 'Brgy. '.fake()->lastName(),
            'ec_name' => fake()->lastName().' Elementary School',
            'families_cum' => $cumFamilies,
            'families_now' => $nowFamilies,
            'persons_cum' => $cumPersons,
            'persons_now' => $nowPersons,
            'origin' => 'Brgy. '.fake()->lastName(),
            'remarks' => fake()->optional()->sentence(),
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function makeAgeDistribution(int $totalPersons): array
    {
        $groups = ['0-5', '6-12', '13-17', '18-35', '36-59', '60-69', '70+'];
        $distribution = [];

        foreach ($groups as $group) {
            $male = fake()->numberBetween(1, 30);
            $female = fake()->numberBetween(1, 30);
            $distribution[$group] = [
                'male_cum' => $male + fake()->numberBetween(0, 10),
                'male_now' => $male,
                'female_cum' => $female + fake()->numberBetween(0, 10),
                'female_now' => $female,
            ];
        }

        return $distribution;
    }

    /** @return array<string, array<string, int>> */
    private function makeVulnerableSectors(): array
    {
        $sectors = ['Pregnant/Lactating', 'Solo Parent', 'PWD', 'Indigenous People', 'Senior Citizen'];
        $data = [];

        foreach ($sectors as $sector) {
            $male = fake()->numberBetween(0, 15);
            $female = fake()->numberBetween(0, 15);
            $data[$sector] = [
                'male_cum' => $male + fake()->numberBetween(0, 5),
                'male_now' => $male,
                'female_cum' => $female + fake()->numberBetween(0, 5),
                'female_now' => $female,
            ];
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function makeOutsideEC(): array
    {
        $cumFamilies = fake()->numberBetween(20, 200);
        $nowFamilies = fake()->numberBetween(5, $cumFamilies);

        return [
            'barangay' => 'Brgy. '.fake()->lastName(),
            'families_cum' => $cumFamilies,
            'families_now' => $nowFamilies,
            'persons_cum' => $cumFamilies * fake()->numberBetween(3, 5),
            'persons_now' => $nowFamilies * fake()->numberBetween(3, 5),
            'origin' => 'Brgy. '.fake()->lastName(),
        ];
    }

    /** @return array<string, mixed> */
    private function makeNonIdp(): array
    {
        $cumFamilies = fake()->numberBetween(10, 100);
        $nowFamilies = fake()->numberBetween(5, $cumFamilies);

        return [
            'barangay' => 'Brgy. '.fake()->lastName(),
            'families_cum' => $cumFamilies,
            'families_now' => $nowFamilies,
            'persons_cum' => $cumFamilies * fake()->numberBetween(3, 5),
            'persons_now' => $nowFamilies * fake()->numberBetween(3, 5),
        ];
    }

    /** @return array<string, mixed> */
    private function makeDamagedHouse(): array
    {
        return [
            'barangay' => 'Brgy. '.fake()->lastName(),
            'totally_damaged' => fake()->numberBetween(0, 20),
            'partially_damaged' => fake()->numberBetween(0, 50),
            'estimated_cost' => fake()->numberBetween(50000, 5000000),
        ];
    }
}
