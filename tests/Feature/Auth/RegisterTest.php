<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alice Martin',
            'email' => 'alice@gmail.com',
            'password' => 'motdepasse-solide',
        ], $overrides);
    }

    public function test_registration_creates_a_student_and_returns_a_token(): void
    {
        $response = $this->postJson('/api/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonPath('data.user.email', 'alice@gmail.com')
            ->assertJsonPath('data.user.role', 'student')
            ->assertJsonPath('data.user.is_academic', false)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password')
            ->assertJsonMissingPath('data.user.remember_token');

        $this->assertMatchesRegularExpression('/^\d+\|meriz_\w+$/', $response->json('data.token'));

        $user = User::firstWhere('email', 'alice@gmail.com');
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertNotSame('motdepasse-solide', $user->password);
        $this->assertTrue(Hash::check('motdepasse-solide', $user->password));
        $this->assertCount(1, $user->tokens);
    }

    public function test_returned_token_gives_access_to_the_profile(): void
    {
        $token = $this->postJson('/api/auth/register', $this->payload())->json('data.token');

        $this->withToken($token)->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'alice@gmail.com');
    }

    public function test_academic_email_marks_the_account_as_academic(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['email' => 'alice@etu.univ-lyon1.fr']))
            ->assertCreated()
            ->assertJsonPath('data.user.is_academic', true)
            ->assertJsonPath('data.user.role', 'student');
    }

    public function test_client_cannot_choose_its_role_or_academic_status(): void
    {
        $this->postJson('/api/auth/register', $this->payload([
            'role' => 'teacher',
            'is_academic' => true,
        ]))->assertCreated();

        $user = User::firstWhere('email', 'alice@gmail.com');
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertFalse($user->is_academic);
    }

    public function test_email_is_normalized_to_lowercase(): void
    {
        $this->postJson('/api/auth/register', $this->payload(['email' => '  Alice@Gmail.COM ']))
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'alice@gmail.com');
    }

    public function test_invalid_input_is_rejected(): void
    {
        $this->postJson('/api/auth/register', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'pas-un-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->postJson('/api/auth/register', $this->payload(['password' => 'court']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->postJson('/api/auth/register', $this->payload(['password' => str_repeat('a', 73)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_email_must_be_unique_regardless_of_case(): void
    {
        User::factory()->create(['email' => 'alice@gmail.com']);

        $this->postJson('/api/auth/register', $this->payload(['email' => 'ALICE@gmail.com']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertDatabaseCount('users', 1);
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/register', $this->payload(['email' => "user{$i}@gmail.com"]))
                ->assertCreated();
        }

        $this->postJson('/api/auth/register', $this->payload(['email' => 'user5@gmail.com']))
            ->assertTooManyRequests();

        $this->assertDatabaseCount('users', 5);
    }
}
