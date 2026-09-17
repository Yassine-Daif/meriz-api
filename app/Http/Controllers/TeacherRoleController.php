<?php

namespace App\Http\Controllers;

use App\Actions\Teacher\RequestTeacherRole;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeacherRoleController extends Controller
{
    public function store(Request $request, RequestTeacherRole $requestTeacherRole): UserResource
    {
        Gate::authorize('becomeTeacher', User::class);

        $user = $request->user();
        $requestTeacherRole->handle($user);

        return new UserResource($user);
    }
}
