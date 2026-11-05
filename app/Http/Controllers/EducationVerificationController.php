<?php

namespace App\Http\Controllers;

use App\Http\Requests\Education\AcceptTeacherInvitationRequest;
use App\Http\Requests\Education\InviteTeacherRequest;
use App\Http\Resources\UserResource;
use App\Models\EducationInstitution;
use App\Notifications\TeacherInvitation;
use App\Services\ActivateVerifiedTeacher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EducationVerificationController extends Controller
{
    public function show(Request $request)
    {
        return response()->json(['data' => [
            // Alimenter uniquement après un raccordement institutionnel validé.
            'providers' => [],
            'referent_institutions' => DB::table('education_referents')->join('education_institutions', 'education_institutions.id', '=', 'education_referents.institution_id')
                ->where('education_referents.user_id', $request->user()->id)->get(['education_institutions.id', 'name', 'country']),
        ]]);
    }

    public function invite(InviteTeacherRequest $request)
    {
        $token = Str::random(64);
        $id = (string) Str::ulid();
        DB::table('education_invitations')->insert([
            'id' => $id, 'institution_id' => $request->validated('institution_id'), 'inviter_id' => $request->user()->id,
            'email' => $request->validated('email'), 'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addHours(48), 'created_at' => now(), 'updated_at' => now(),
        ]);
        try {
            $institution = EducationInstitution::findOrFail($request->validated('institution_id'));
            Notification::route('mail', $request->validated('email'))->notify(new TeacherInvitation($token, $institution->name));
        } catch (Throwable $exception) {
            DB::table('education_invitations')->where('id', $id)->delete();
            throw $exception;
        }

        return response()->json(['message' => 'Invitation envoyée.'], 202);
    }

    public function accept(AcceptTeacherInvitationRequest $request, ActivateVerifiedTeacher $activate): UserResource
    {
        $user = $request->user();
        DB::transaction(function () use ($request, $user, $activate) {
            $invitation = DB::table('education_invitations')->where('token_hash', hash('sha256', $request->validated('token')))->lockForUpdate()->first();
            if (! $invitation || $invitation->accepted_at || now()->gte($invitation->expires_at)
                || ! hash_equals($invitation->email, mb_strtolower($user->email))
                || ! DB::table('education_referents')->where('user_id', $invitation->inviter_id)->where('institution_id', $invitation->institution_id)->exists()) {
                throw ValidationException::withMessages(['token' => 'Invitation invalide, expirée ou destinée à une autre adresse.']);
            }
            $user->forceFill([
                'usage_category' => 'teacher', 'teacher_verified_at' => now(), 'teacher_verification_source' => 'institution_referent',
                'education_institution_id' => $invitation->institution_id,
            ])->save();
            DB::table('education_invitations')->where('id', $invitation->id)->update(['accepted_at' => now(), 'updated_at' => now()]);
            $activate->handle($user);
        });

        return new UserResource($user);
    }
}
