<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'name' => fake()->lastName(),
            'first_name' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Student,
            'is_academic' => false,
        ];
    }

    /**
     * Indicate that the user has an academic email address.
     */
    public function academic(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => fake()->unique()->userName().'@univ-lyon1.fr',
            'is_academic' => true,
        ]);
    }

    /**
     * Indicate that the user fills and shares both profile fields.
     */
    public function withSharedProfile(): static
    {
        return $this->state(fn (array $attributes) => [
            'bio' => fake()->sentence(),
            'bio_shared' => true,
            'contact' => 'discord: '.fake()->userName(),
            'contact_shared' => true,
        ]);
    }

    /**
     * Indicate that the user is a teacher.
     */
    public function teacher(): static
    {
        return $this->academic()->state(fn (array $attributes) => [
            'role' => UserRole::Teacher,
        ]);
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
}
