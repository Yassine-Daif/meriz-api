<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_revokes_only_the_current_token(): void
    {
        $user = User::factory()->create();
        $current = $user->createToken('web')->plainTextToken;
        $other = $user->createToken('tauri')->plainTextToken;

        $this->withToken($current)->postJson('/api/auth/logout')->assertNoContent();

        $this->assertSame(['tauri'], $user->tokens()->pluck('name')->all());

        $this->app['auth']->forgetGuards();
        $this->withToken($current)->getJson('/api/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken($other)->getJson('/api/me')->assertOk();
    }

    public function test_logout_requires_a_token(): void
    {
        $this->postJson('/api/auth/logout')->assertUnauthorized();
    }
}
