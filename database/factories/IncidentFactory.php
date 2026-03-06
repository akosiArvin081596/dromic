<?php

namespace Database\Factories;

use App\Enums\IncidentCategory;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Models\Incident;
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
        $category = fake()->randomElement(IncidentCategory::cases());
        $identifier = match ($category) {
            IncidentCategory::TropicalCyclone => fake()->randomElement(['Aghon', 'Betty', 'Carina', 'Dodong']),
            IncidentCategory::Earthquake => 'Intensity '.fake()->randomElement(['III', 'IV', 'V', 'VI']),
            default => null,
        };

        return [
            'name' => Incident::composeName($category, $identifier),
            'category' => $category,
            'identifier' => $identifier,
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

    public function category(IncidentCategory $category, ?string $identifier = null): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => $category,
            'identifier' => $identifier,
            'name' => Incident::composeName($category, $identifier),
        ]);
    }
}
