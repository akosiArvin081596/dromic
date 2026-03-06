<?php

namespace Database\Factories;

use App\Enums\AugmentationType;
use App\Models\CityMunicipality;
use App\Models\Incident;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RequestLetter>
 */
class RequestLetterFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'user_id' => User::factory(),
            'city_municipality_id' => CityMunicipality::factory(),
            'file_path' => 'request-letters/'.fake()->uuid().'.pdf',
            'original_filename' => fake()->words(3, true).'.pdf',
            'augmentation_items' => [
                [
                    'type' => fake()->randomElement(AugmentationType::cases())->value,
                    'quantity' => fake()->numberBetween(10, 100),
                ],
            ],
            'status' => 'pending',
        ];
    }
}
