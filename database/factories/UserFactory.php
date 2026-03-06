<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\CityMunicipality;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Lgu,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Admin,
            'region_id' => null,
            'province_id' => null,
            'city_municipality_id' => null,
        ]);
    }

    public function regional(?Region $region = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Regional,
            'region_id' => $region?->id ?? Region::factory(),
            'province_id' => null,
            'city_municipality_id' => null,
        ]);
    }

    public function provincial(?Province $province = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Provincial,
            'province_id' => $province?->id ?? Province::factory(),
            'city_municipality_id' => null,
        ]);
    }

    public function lgu(?CityMunicipality $cityMunicipality = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Lgu,
            'city_municipality_id' => $cityMunicipality?->id ?? CityMunicipality::factory(),
            'province_id' => null,
        ]);
    }

    public function escort(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::Escort,
            'region_id' => null,
            'province_id' => null,
            'city_municipality_id' => null,
        ]);
    }

    public function regionalDirector(?Region $region = null): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => UserRole::RegionalDirector,
            'region_id' => $region?->id ?? Region::factory(),
            'province_id' => null,
            'city_municipality_id' => null,
        ]);
    }
}
