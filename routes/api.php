<?php

use App\Http\Controllers\AssignmentController;
use App\Http\Controllers\AssignmentImageController;
use App\Http\Controllers\AssignmentWorkController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\ClassroomController;
use App\Http\Controllers\ClassroomMembershipController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupDocumentController;
use App\Http\Controllers\GroupMembershipController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonMediumController;
use App\Http\Controllers\LiveTrackingController;
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

    // Suivi en direct : lecture seule, hormis le drapeau.
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/assignments/{assignment}/live', [LiveTrackingController::class, 'index']);
        Route::get('/assignments/{assignment}/live/{student}', [LiveTrackingController::class, 'show']);
        // Ping d'observation : n'écrit que la trace de lecture.
        Route::post('/assignments/{assignment}/live/{student}/seen', [LiveTrackingController::class, 'seen']);
    });

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/assignments/{assignment}/live-tracking', [LiveTrackingController::class, 'enable']);
        Route::delete('/assignments/{assignment}/live-tracking', [LiveTrackingController::class, 'disable']);
    });

    Route::middleware('throttle:20,1')->group(function () {
        Route::post('/assignments/{assignment}/image', [AssignmentImageController::class, 'store']);
        // Commencer un devoir : crée le travail rattaché, avec base ou non.
        Route::post('/assignments/{assignment}/start', [AssignmentWorkController::class, 'start']);
        // Ancien nom, conservé le temps que l'application passe à /start.
        Route::post('/assignments/{assignment}/copy', [AssignmentWorkController::class, 'start']);
    });

    // Groupes d'élèves
    Route::post('/groups/join', [GroupMembershipController::class, 'join'])
        ->middleware('throttle:join-group');

    Route::get('/groups', [GroupController::class, 'index']);
    Route::get('/groups/{group}', [GroupController::class, 'show']);
    Route::get('/groups/{group}/documents', [GroupDocumentController::class, 'index']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/groups', [GroupController::class, 'store']);
        Route::patch('/groups/{group}', [GroupController::class, 'update']);
        Route::delete('/groups/{group}', [GroupController::class, 'destroy']);
        Route::post('/groups/{group}/code', [GroupController::class, 'regenerateCode']);
        Route::delete('/groups/{group}/members/{participant}', [GroupMembershipController::class, 'remove']);
        Route::delete('/groups/{group}/membership', [GroupMembershipController::class, 'leave']);
        Route::post('/groups/{group}/documents', [GroupDocumentController::class, 'store']);
    });

    // Commentaires d'un travail : généraux, ou posés sur le schéma.
    Route::get('/documents/{document}/comments', [CommentController::class, 'index']);

    Route::middleware('throttle:60,1')->group(function () {
        Route::post('/documents/{document}/comments', [CommentController::class, 'store']);
        Route::post('/comments/{comment}/resolution', [CommentController::class, 'resolve']);
        Route::delete('/comments/{comment}/resolution', [CommentController::class, 'reopen']);
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    });

    Route::apiResource('documents', DocumentController::class)->only(['index', 'show']);
    Route::apiResource('documents', DocumentController::class)->only(['store', 'update', 'destroy'])
        ->middleware('throttle:60,1');
});
