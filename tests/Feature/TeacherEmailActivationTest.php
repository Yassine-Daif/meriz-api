<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\VerifyEmailAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherEmailActivationTest extends TestCase
{
    use RefreshDatabase;

    private function signup(array $extra = []): array
    {
        return [...['name' => 'Professeur', 'first_name' => 'Claire', 'email' => 'claire@univ-reims.fr', 'password' => 'motdepasse-solide', 'usage_category' => 'teacher'], ...$extra];
    }

    public function test_teacher_receives_an_activation_link_and_becomes_teacher_on_confirmation(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/register', $this->signup())->assertCreated()
            ->assertJsonPath('data.user.role', 'student')->assertJsonPath('data.user.is_academic', true);
        $user = User::firstWhere('email', 'claire@univ-reims.fr');
        Notification::assertSentTo($user, VerifyEmailAddress::class, function ($notification) use ($user) {
            $mail = $notification->toMail($user);
            $this->assertSame('Activez votre espace professeur Meriz', $mail->subject);
            $this->assertSame('Activer mon espace professeur', $mail->actionText);
            $this->get($mail->actionUrl)->assertRedirect();

            return true;
        });
        $this->assertSame(UserRole::Teacher, $user->fresh()->role);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->teacher_verified_at);
    }

    public function test_teacher_signup_requires_an_academic_email_but_student_signup_does_not(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/register', $this->signup(['email' => 'claire@gmail.com', 'is_academic' => true]))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        Notification::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
        $this->postJson('/api/auth/register', $this->signup(['email' => 'claire@gmail.com', 'usage_category' => 'student']))
            ->assertCreated()->assertJsonPath('data.user.role', 'student');
    }

    public function test_high_school_academic_address_is_supported(): void
    {
        Notification::fake();
        $this->postJson('/api/auth/register', $this->signup(['email' => 'claire@ac-reims.fr']))
            ->assertCreated()->assertJsonPath('data.user.is_academic', true);
    }

    public function test_academic_student_is_not_promoted_without_choosing_teacher(): void
    {
        $user = User::factory()->academic()->unverified()->create(['usage_category' => 'student']);
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]))->assertRedirect();
        $this->assertSame(UserRole::Student, $user->fresh()->role);
        Sanctum::actingAs($user->fresh());
        $this->patchJson('/api/me', ['usage_category' => 'teacher'])->assertOk()->assertJsonPath('data.role', 'teacher');
    }

    public function test_existing_verified_pending_teacher_is_activated_on_profile_reload_or_login(): void
    {
        $user = User::factory()->academic()->create(['usage_category' => 'teacher']);
        Sanctum::actingAs($user);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.role', 'teacher');
        $other = User::factory()->academic()->create(['usage_category' => 'teacher']);
        $this->postJson('/api/auth/login', ['email' => $other->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.user.role', 'teacher');
    }

    public function test_unverified_teacher_is_not_activated_by_login_or_profile_access(): void
    {
        $user = User::factory()->academic()->unverified()->create(['usage_category' => 'teacher']);
        Sanctum::actingAs($user);
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.role', 'student');
        $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertOk()->assertJsonPath('data.user.role', 'student');
    }

    public function test_expired_and_tampered_links_do_not_activate_a_teacher(): void
    {
        $user = User::factory()->academic()->unverified()->create(['usage_category' => 'teacher']);
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect();
        $valid = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($valid.'changed')->assertRedirect();
        $this->assertSame(UserRole::Student, $user->fresh()->role);
        $this->assertNull($user->fresh()->email_verified_at);
    }
}
