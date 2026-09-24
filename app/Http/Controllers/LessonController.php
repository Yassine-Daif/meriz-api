<?php

namespace App\Http\Controllers;

use App\Actions\Lessons\ChangeLessonState;
use App\Actions\Lessons\CreateLesson;
use App\Enums\LessonStatus;
use App\Http\Requests\Lessons\IndexLessonsRequest;
use App\Http\Requests\Lessons\StoreLessonRequest;
use App\Http\Requests\Lessons\UpdateLessonRequest;
use App\Http\Resources\LessonResource;
use App\Models\Classroom;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * {classroom} et {lesson} ne sont résolus que parmi ce que l'utilisateur
 * peut voir (voir AppServiceProvider). La Policy revérifie.
 */
class LessonController extends Controller
{
    /**
     * Cours d'une classe : tous pour son prof, les publiés pour un membre.
     */
    public function indexForClassroom(Request $request, Classroom $classroom): AnonymousResourceCollection
    {
        Gate::authorize('view', $classroom);

        $query = $classroom->lessons()->summary();

        if (! $classroom->isTaughtBy($request->user())) {
            $query->where('status', LessonStatus::Published);
        }

        return $this->collection($query);
    }

    /**
     * Mes cours publiés, dans toutes les classes dont je suis membre.
     */
    public function index(IndexLessonsRequest $request): AnonymousResourceCollection
    {
        $query = Lesson::summary()
            ->where('status', LessonStatus::Published)
            ->whereHas('classroom.members', fn (Builder $m) => $m->whereKey($request->user()->id));

        if ($classroomId = $request->validated('classroom')) {
            $query->where('classroom_id', $classroomId);
        }

        return $this->collection($query);
    }

    public function store(StoreLessonRequest $request, Classroom $classroom, CreateLesson $create): JsonResponse
    {
        Gate::authorize('create', [Lesson::class, $classroom]);

        $lesson = $create->handle($classroom, $request->validated());

        return $this->detailed($lesson)->response()->setStatusCode(201);
    }

    public function show(Lesson $lesson): LessonResource
    {
        Gate::authorize('view', $lesson);

        return $this->detailed($lesson);
    }

    public function update(UpdateLessonRequest $request, Lesson $lesson): LessonResource
    {
        Gate::authorize('manage', $lesson);

        $lesson->update($request->validated());

        return $this->detailed($lesson);
    }

    public function publish(Lesson $lesson, ChangeLessonState $state): LessonResource
    {
        Gate::authorize('manage', $lesson);

        return $this->detailed($state->publish($lesson));
    }

    public function unpublish(Lesson $lesson, ChangeLessonState $state): LessonResource
    {
        Gate::authorize('manage', $lesson);

        return $this->detailed($state->unpublish($lesson));
    }

    public function destroy(Lesson $lesson): Response
    {
        Gate::authorize('manage', $lesson);

        $lesson->delete();

        return response()->noContent();
    }

    private function detailed(Lesson $lesson): LessonResource
    {
        $lesson->load('media')->loadCount('media');

        return (new LessonResource($lesson))->detailed();
    }

    /**
     * @param  Builder<Lesson>|HasMany<Lesson, Classroom>  $query
     */
    private function collection($query): AnonymousResourceCollection
    {
        return LessonResource::collection(
            $query->withCount('media')
                ->orderByDesc('lessons.created_at')
                ->get()
        );
    }
}
