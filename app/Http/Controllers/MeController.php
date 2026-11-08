<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\ActivateVerifiedTeacher;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        app(ActivateVerifiedTeacher::class)->handle($request->user());

        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $user->update($request->validated());
        app(ActivateVerifiedTeacher::class)->handle($user);

        return new UserResource($user);
    }
}
