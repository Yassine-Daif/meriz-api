<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\EducationInstitution;
use App\Models\User;
use App\Notifications\TeacherInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EducationVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function invitation(User $recipient): array
    {
        $institution = new EducationInstitution;
        $institution->forceFill(['name' => 'Établissement de test', 'country' => 'FR'])->save();
        $referent = User::factory()->create();
        DB::table('education_referents')->insert(['institution_id' => $institution->id, 'user_id' => $referent->id, 'created_at' => now(), 'updated_at' => now()]);
        $token = Str::random(64);
        DB::table('education_invitations')->insert([
            'id' => (string) Str::ulid(), 'institution_id' => $institution->id, 'inviter_id' => $referent->id,
            'email' => $recipient->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay(),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$token, $referent, $institution];
    }

    public function test_verified_personal_email_can_receive_teacher_rights_from_a_trusted_invitation(): void
    {
        $user = User::factory()->create(['is_academic' => false]);
        [$token] = $this->invitation($user);
        Sanctum::actingAs($user);
        $this->postJson('/api/me/education/accept-invitation', ['token' => $token])
            ->assertOk()->assertJsonPath('data.role', 'teacher')->assertJsonPath('data.usage_category', 'teacher');
        $this->assertSame(UserRole::Teacher, $user->fresh()->role);
        $this->postJson('/api/me/education/accept-invitation', ['token' => $token])->assertUnprocessable();
    }

    public function test_email_confirmation_automatically_activates_an_already_attested_teacher(): void
    {
        $user = User::factory()->unverified()->create();
        [$token] = $this->invitation($user);
        Sanctum::actingAs($user);
        $this->postJson('/api/me/education/accept-invitation', ['token' => $token])->assertOk()->assertJsonPath('data.role', 'student');
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]))->assertRedirect();
        $this->assertSame(UserRole::Teacher, $user->fresh()->role);
    }

    public function test_invitation_is_bound_to_email_expiry_and_active_referent(): void
    {
        $user = User::factory()->create();
        [$token, $referent] = $this->invitation($user);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/me/education/accept-invitation', ['token' => $token])->assertUnprocessable();
        Sanctum::actingAs($user);
        DB::table('education_referents')->where('user_id', $referent->id)->delete();
        $this->postJson('/api/me/education/accept-invitation', ['token' => $token])->assertUnprocessable();
        $this->assertNull($user->fresh()->teacher_verified_at);
    }

    public function test_only_verified_referent_can_send_invitations_and_proofs_cannot_be_forged(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        [, $referent, $institution] = $this->invitation($user);
        Sanctum::actingAs($user);
        $this->postJson('/api/me/education/invitations', ['institution_id' => $institution->id, 'email' => 'teacher@example.org'])->assertForbidden();
        $this->patchJson('/api/me', ['teacher_verified_at' => now()->toIso8601String(), 'teacher_verification_source' => 'renater', 'education_institution_id' => $institution->id])
            ->assertOk()->assertJsonPath('data.teacher_verified_at', null);
        Sanctum::actingAs($referent);
        $this->postJson('/api/me/education/invitations', ['institution_id' => $institution->id, 'email' => 'teacher@example.org'])
            ->assertAccepted()->assertJsonMissingPath('token');
        Notification::assertSentOnDemand(TeacherInvitation::class);
    }

    public function test_expired_invitation_never_attests_the_account(): void
    {
        $user = User::factory()->create();
        [$token] = $this->invitation($user);
        DB::table('education_invitations')->update(['expires_at' => now()->subMinute()]);
        Sanctum::actingAs($user);
        $this->postJson('/api/me/education/accept-invitation', ['token' => $token])->assertUnprocessable();
        $this->assertNull($user->fresh()->teacher_verified_at);
    }
}
