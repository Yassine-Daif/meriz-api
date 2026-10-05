<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use App\Services\JoinCodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creator_id' => User::factory(),
            'name' => 'Groupe '.fake()->bothify('?#'),
            'join_code' => app(JoinCodeGenerator::class)->generate(Group::class),
        ];
    }

    /**
     * Le créateur est aussi membre, comme à la création réelle.
     */
    public function configure(): static
    {
        return $this->afterCreating(
            fn (Group $group) => $group->members()->syncWithoutDetaching($group->creator_id)
        );
    }
}
