<?php

namespace Database\Factories;

use App\Models\CityMunicipality;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Barangay>
 */
class BarangayFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'psgc_code' => fake()->unique()->numerify('##########'),
            'name' => 'Brgy. '.fake()->lastName(),
            'city_municipality_id' => CityMunicipality::factory(),
        ];
    }
}
