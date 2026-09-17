<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\TeacherRoleController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok']));

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

Route::middleware('auth:sanctum')->prefix('me')->group(function () {
    Route::get('/', [MeController::class, 'show']);
    Route::post('/teacher-role', [TeacherRoleController::class, 'store'])->middleware('throttle:6,1');
});
