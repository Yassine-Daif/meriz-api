<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassroomMembershipController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\TeacherRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('me')->group(function () {
        Route::get('/', [MeController::class, 'show']);
        Route::patch('/', [MeController::class, 'update'])->middleware('throttle:60,1');
        Route::post('/teacher-role', [TeacherRoleController::class, 'store'])->middleware('throttle:6,1');
    });

    // join est déclarée avant {classroom} pour ne pas être prise pour un id.
    Route::post('/classrooms/join', [ClassroomMembershipController::class, 'join'])
        ->middleware('throttle:join-classroom');

    Route::get('/classrooms', [ClassroomController::class, 'index']);
    Route::get('/classrooms/{classroom}', [ClassroomController::class, 'show']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/classrooms', [ClassroomController::class, 'store']);
        Route::patch('/classrooms/{classroom}', [ClassroomController::class, 'update']);
        Route::delete('/classrooms/{classroom}', [ClassroomController::class, 'destroy']);
        Route::post('/classrooms/{classroom}/code', [ClassroomController::class, 'regenerateCode']);
        Route::delete('/classrooms/{classroom}/members/{member}', [ClassroomMembershipController::class, 'remove']);
        Route::delete('/classrooms/{classroom}/membership', [ClassroomMembershipController::class, 'leave']);
    });

    Route::apiResource('documents', DocumentController::class)->only(['index', 'show']);
    Route::apiResource('documents', DocumentController::class)->only(['store', 'update', 'destroy'])
        ->middleware('throttle:60,1');
});
