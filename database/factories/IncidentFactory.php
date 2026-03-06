<?php

namespace Database\Factories;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Incident>
 */
class IncidentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        $incidentNames = [
            'Typhoon Aghon',
            'Tropical Storm Betty',
            'Flooding - Heavy Rainfall',
            'Earthquake Intensity V',
            'Landslide - Continuous Rain',
            'Typhoon Carina',
            'Tropical Depression Dodong',
            'Storm Surge Warning',
        ];

        return [
            'name' => fake()->randomElement($incidentNames),
            'type' => IncidentType::Massive,
            'created_by' => User::factory(),
            'description' => fake()->paragraph(2),
            'status' => IncidentStatus::Active,
        ];
    }

    public function local(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => IncidentType::Local,
        ]);
    }

    public function massive(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => IncidentType::Massive,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::Closed,
        ]);
    }
}
