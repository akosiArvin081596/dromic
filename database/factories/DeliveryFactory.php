<?php

namespace Database\Factories;

use App\Enums\AugmentationType;
use App\Models\RequestLetter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Delivery>
 */
class DeliveryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'request_letter_id' => RequestLetter::factory(),
            'escort_user_id' => null,
            'recorded_by' => User::factory(),
            'delivery_items' => [
                [
                    'type' => fake()->randomElement(AugmentationType::cases())->value,
                    'quantity' => fake()->numberBetween(5, 50),
                ],
            ],
            'delivery_date' => fake()->date(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
