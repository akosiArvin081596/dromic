<?php

namespace Database\Factories;

use App\Models\Province;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CityMunicipality>
 */
class CityMunicipalityFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'psgc_code' => fake()->unique()->numerify('##########'),
            'name' => fake()->city(),
            'province_id' => Province::factory(),
        ];
    }
}
