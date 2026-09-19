<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_requires_a_token(): void
    {
        $this->getJson('/api/me')
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_profile_without_json_accept_header_still_answers_401_json(): void
    {
        $this->get('/api/me')
            ->assertUnauthorized()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('meriz_1|faux-jeton')->getJson('/api/me')->assertUnauthorized();
    }

    public function test_profile_returns_the_user_without_sensitive_fields(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('web')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/me');

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.role', 'student');

        $this->assertEqualsCanonicalizing(
            ['id', 'name', 'first_name', 'email', 'role', 'is_academic', 'bio', 'bio_shared', 'contact', 'contact_shared', 'avatar_bg', 'avatar_fg', 'email_verified_at', 'created_at'],
            array_keys($response->json('data')),
        );
    }
}
