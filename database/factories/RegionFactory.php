<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Region>
 */
class RegionFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'psgc_code' => fake()->unique()->numerify('##########'),
            'name' => 'Region '.fake()->unique()->randomNumber(2),
        ];
    }
}
