<?php

namespace App\Http\Controllers;

use App\Actions\Classrooms\CreateClassroom;
use App\Actions\Classrooms\RegenerateJoinCode;
use App\Http\Requests\Classrooms\ClassroomNameRequest;
use App\Http\Requests\Classrooms\IndexClassroomsRequest;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Le paramètre {classroom} n'est cherché que parmi les classes visibles par
 * l'utilisateur connecté (voir AppServiceProvider). La Policy revérifie.
 */
class ClassroomController extends Controller
{
    public function index(IndexClassroomsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Classroom::class);

        $user = $request->user();

        $classrooms = match ($request->validated('role')) {
            'teacher' => $user->taughtClassrooms(),
            'student' => $user->classrooms(),
            default => Classroom::visibleTo($user),
        };

        return ClassroomResource::collection(
            $classrooms->with('teacher')->withCount('members')->orderBy('name')->orderBy('classrooms.id')->get()
        );
    }

    public function store(ClassroomNameRequest $request, CreateClassroom $createClassroom): JsonResponse
    {
        Gate::authorize('create', Classroom::class);

        $classroom = $createClassroom->handle($request->user(), $request->validated('name'));

        return $this->resource($classroom)->response()->setStatusCode(201);
    }

    public function show(Classroom $classroom): ClassroomResource
    {
        Gate::authorize('view', $classroom);

        return $this->resource($classroom);
    }

    public function update(ClassroomNameRequest $request, Classroom $classroom): ClassroomResource
    {
        Gate::authorize('manage', $classroom);

        $classroom->update($request->validated());

        return $this->resource($classroom);
    }

    public function regenerateCode(Classroom $classroom, RegenerateJoinCode $regenerate): ClassroomResource
    {
        Gate::authorize('manage', $classroom);

        return $this->resource($regenerate->handle($classroom));
    }

    public function destroy(Classroom $classroom): Response
    {
        Gate::authorize('manage', $classroom);

        $classroom->delete();

        return response()->noContent();
    }

    private function resource(Classroom $classroom): ClassroomResource
    {
        $classroom->load([
            'teacher',
            'members' => fn ($query) => $query->orderBy('name')->orderBy('first_name'),
        ])->loadCount('members');

        return new ClassroomResource($classroom);
    }
}
