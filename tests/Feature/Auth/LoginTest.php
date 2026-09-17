<?php

namespace Tests\Feature\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'alice@gmail.com',
            'password' => 'motdepasse-solide',
        ]);
    }

    public function test_valid_credentials_return_a_usable_token(): void
    {
        $user = $this->user();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'Alice@Gmail.com',
            'password' => 'motdepasse-solide',
            'device_name' => 'Tauri Windows',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonMissingPath('data.user.password');

        $this->assertSame('Tauri Windows', $user->tokens()->first()->name);

        $this->withToken($response->json('data.token'))->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_wrong_password_and_unknown_email_give_the_same_answer(): void
    {
        $this->user();

        $wrongPassword = $this->postJson('/api/auth/login', [
            'email' => 'alice@gmail.com',
            'password' => 'mauvais-mot-de-passe',
        ]);

        $unknownEmail = $this->postJson('/api/auth/login', [
            'email' => 'inconnu@gmail.com',
            'password' => 'motdepasse-solide',
        ]);

        foreach ([$wrongPassword, $unknownEmail] as $response) {
            $response->assertUnprocessable()
                ->assertJsonMissingPath('data')
                ->assertExactJson([
                    'message' => AuthenticateUser::FAILED_MESSAGE,
                    'errors' => ['email' => [AuthenticateUser::FAILED_MESSAGE]],
                ]);
        }

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_missing_fields_are_rejected(): void
    {
        $this->postJson('/api/auth/login', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_is_rate_limited(): void
    {
        $this->user();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'alice@gmail.com',
                'password' => 'mauvais-mot-de-passe',
            ])->assertUnprocessable();
        }

        // Même le bon mot de passe est bloqué une fois la limite atteinte.
        $this->postJson('/api/auth/login', [
            'email' => 'alice@gmail.com',
            'password' => 'motdepasse-solide',
        ])->assertTooManyRequests();
    }
}
