<?php

namespace Database\Factories;

use App\Models\Province;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Conversation>
 */
class ConversationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => 'dm',
            'province_id' => null,
            'dm_key' => null,
        ];
    }

    public function group(?Province $province = null): static
    {
        return $this->state(fn () => [
            'type' => 'group',
            'province_id' => $province?->id ?? Province::factory(),
            'dm_key' => null,
        ]);
    }

    public function dm(User $user1, User $user2): static
    {
        $ids = [min($user1->id, $user2->id), max($user1->id, $user2->id)];

        return $this->state(fn () => [
            'type' => 'dm',
            'province_id' => null,
            'dm_key' => "dm-{$ids[0]}-{$ids[1]}",
        ]);
    }
}
