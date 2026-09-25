<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AssignmentCopyController;
use App\Http\Controllers\AssignmentImageController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassroomMembershipController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonMediumController;
use App\Http\Controllers\MeController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\SubmissionController;
use App\Http\Controllers\SubmissionGradeController;
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

    // Devoirs
    Route::get('/classrooms/{classroom}/assignments', [AssignmentController::class, 'indexForClassroom']);
    Route::get('/assignments', [AssignmentController::class, 'index']);
    Route::get('/assignments/{assignment}', [AssignmentController::class, 'show']);
    Route::get('/assignments/{assignment}/image', [AssignmentImageController::class, 'show']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/classrooms/{classroom}/assignments', [AssignmentController::class, 'store']);
        Route::patch('/assignments/{assignment}', [AssignmentController::class, 'update']);
        Route::delete('/assignments/{assignment}', [AssignmentController::class, 'destroy']);
        Route::post('/assignments/{assignment}/publication', [AssignmentController::class, 'publish']);
        Route::delete('/assignments/{assignment}/publication', [AssignmentController::class, 'unpublish']);
        Route::post('/assignments/{assignment}/solution-release', [AssignmentController::class, 'releaseSolution']);
        Route::delete('/assignments/{assignment}/solution-release', [AssignmentController::class, 'withholdSolution']);
        Route::delete('/assignments/{assignment}/image', [AssignmentImageController::class, 'destroy']);
    });

    // Vues d'ensemble, lecture seule
    Route::get('/overview/to-grade', [OverviewController::class, 'toGrade']);
    Route::get('/overview/my-assignments', [OverviewController::class, 'myAssignments']);

    // Cours
    Route::get('/classrooms/{classroom}/lessons', [LessonController::class, 'indexForClassroom']);
    Route::get('/lessons', [LessonController::class, 'index']);
    Route::get('/lessons/{lesson}', [LessonController::class, 'show']);
    Route::get('/lessons/{lesson}/media/{medium}', [LessonMediumController::class, 'show']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/classrooms/{classroom}/lessons', [LessonController::class, 'store']);
        Route::patch('/lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('/lessons/{lesson}', [LessonController::class, 'destroy']);
        Route::post('/lessons/{lesson}/publication', [LessonController::class, 'publish']);
        Route::delete('/lessons/{lesson}/publication', [LessonController::class, 'unpublish']);
        Route::delete('/lessons/{lesson}/media/{medium}', [LessonMediumController::class, 'destroy']);
    });

    Route::post('/lessons/{lesson}/media', [LessonMediumController::class, 'store'])
        ->middleware('throttle:20,1');

    // Rendus
    Route::get('/assignments/{assignment}/submission', [SubmissionController::class, 'mine']);
    Route::get('/assignments/{assignment}/submissions', [SubmissionController::class, 'index']);
    Route::get('/submissions/{submission}', [SubmissionController::class, 'show']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::put('/assignments/{assignment}/submission', [SubmissionController::class, 'store']);
        Route::post('/submissions/{submission}/grade', [SubmissionGradeController::class, 'store']);
        Route::delete('/submissions/{submission}/grade', [SubmissionGradeController::class, 'destroy']);
    });

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/assignments/{assignment}/image', [AssignmentImageController::class, 'store']);
        Route::post('/assignments/{assignment}/copy', [AssignmentCopyController::class, 'store']);
    });

    Route::apiResource('documents', DocumentController::class)->only(['index', 'show']);
    Route::apiResource('documents', DocumentController::class)->only(['store', 'update', 'destroy'])
        ->middleware('throttle:60,1');
});
