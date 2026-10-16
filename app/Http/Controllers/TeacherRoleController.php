<?php

namespace App\Http\Controllers;

use App\Actions\Teacher\GrantTeacherRole;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Passer en mode prof. Les deux conditions — adresse scolaire et email
 * vérifié — sont dans UserPolicy::becomeTeacher, et le Gate les applique
 * avant qu'on écrive quoi que ce soit.
 */
class TeacherRoleController extends Controller
{
    public function store(Request $request, GrantTeacherRole $grant): UserResource
    {
        Gate::authorize('becomeTeacher', User::class);

        $user = $request->user();
        $grant->handle($user);

        return new UserResource($user);
    }
}
