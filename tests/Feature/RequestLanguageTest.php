<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordLink;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestLanguageTest extends TestCase
{
    use RefreshDatabase;

    public function test_errors_follow_the_requested_language_and_fall_back_to_french(): void
    {
        $this->withHeader('Accept-Language', 'en-US,en;q=0.9')->getJson('/api/me')
            ->assertUnauthorized()->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', 'Unauthenticated.');

        $this->withHeader('Accept-Language', 'fr-FR')->getJson('/api/me')
            ->assertUnauthorized()->assertJsonPath('message', 'Non authentifié.');

        $this->withHeader('Accept-Language', 'zz')->getJson('/api/me')
            ->assertUnauthorized()->assertHeader('Content-Language', 'fr')
            ->assertJsonPath('message', 'Non authentifié.');
    }

    public function test_validation_is_in_english_without_changing_field_keys(): void
    {
        $this->withHeader('Accept-Language', 'en')->postJson('/api/auth/register', [])
            ->assertUnprocessable()->assertJsonValidationErrors(['name', 'first_name', 'email', 'password'])
            ->assertJsonPath('errors.first_name.0', 'The first name field is required.');
    }

    public function test_user_content_and_access_rules_are_not_translated(): void
    {
        $user = User::factory()->create(['name' => 'Réessayer', 'first_name' => 'Bonjour']);
        Sanctum::actingAs($user);

        $this->withHeader('Accept-Language', 'en')->getJson('/api/me')
            ->assertOk()->assertJsonPath('data.name', 'Réessayer')
            ->assertJsonPath('data.first_name', 'Bonjour');

        $this->postJson('/api/classrooms', ['name' => 'Ma classe'])
            ->assertForbidden()->assertJsonPath('message', 'Only a teacher account can create a class.');
    }

    public function test_verification_email_keeps_the_request_language(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        Sanctum::actingAs($user);

        $this->withHeader('Accept-Language', 'en')->postJson('/api/auth/email/verification-notification')
            ->assertAccepted()->assertJsonPath('message', 'Verification link sent.');

        Notification::assertSentTo($user, VerifyEmailAddress::class, fn ($notification) => $notification->locale === 'en');
        app()->setLocale('en');
        $message = (new VerifyEmailAddress)->toMail($user);
        $this->assertSame('Verify your email address', $message->subject);
        $this->assertSame('Verify my email', $message->actionText);
        $this->assertStringContainsString('signature=', $message->actionUrl);
    }

    public function test_password_email_is_localized_and_opens_the_english_application(): void
    {
        $user = User::factory()->create();
        config(['frontend.url' => 'https://app.meriz.test']);
        app()->setLocale('en');
        $message = (new ResetPasswordLink('diagnostic-token'))->toMail($user);

        $this->assertSame('Reset your password', $message->subject);
        $this->assertSame('Choose a new password', $message->actionText);
        $this->assertStringStartsWith('https://app.meriz.test/', $message->actionUrl);
        $this->assertStringContainsString('lang=en', $message->actionUrl);
        $this->assertStringContainsString('token=diagnostic-token', $message->actionUrl);
    }

    public function test_nested_objects_and_user_text_keep_their_original_shape(): void
    {
        Route::get('/api/diagnostic-language', fn () => response()->json([
            'message' => 'Lien de vérification envoyé.',
            'data' => (object) ['name' => 'Lien de vérification envoyé.', 'empty' => (object) []],
        ]));

        $response = $this->withHeader('Accept-Language', 'en')->getJson('/api/diagnostic-language')
            ->assertOk()->assertJsonPath('message', 'Verification link sent.')
            ->assertJsonPath('data.name', 'Lien de vérification envoyé.');

        $this->assertInstanceOf(\stdClass::class, json_decode($response->getContent())->data->empty);
    }
}
