<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Actions\Auth\RegisterUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, RegisterUser $register): JsonResponse
    {
        $result = $register->handle($request->validated());

        return (new AuthTokenResource($result))
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request, AuthenticateUser $authenticate): AuthTokenResource
    {
        $result = $authenticate->handle(
            $request->validated('email'),
            $request->validated('password'),
            $request->validated('device_name'),
        );

        return new AuthTokenResource($result);
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
