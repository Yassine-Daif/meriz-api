<?php

namespace App\Http\Controllers;

use App\Actions\Assignments\ChangeAssignmentState;
use App\Actions\Assignments\CreateAssignment;
use App\Actions\Assignments\UpdateAssignment;
use App\Enums\AssignmentStatus;
use App\Http\Requests\Assignments\IndexAssignmentsRequest;
use App\Http\Requests\Assignments\StoreAssignmentRequest;
use App\Http\Requests\Assignments\UpdateAssignmentRequest;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use App\Models\Classroom;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * {classroom} et {assignment} ne sont cherchés que parmi ce que
 * l'utilisateur peut voir (voir AppServiceProvider). La Policy revérifie.
 */
class AssignmentController extends Controller
{
    /**
     * Devoirs d'une classe : tous pour son prof, les publiés pour un membre.
     */
    public function indexForClassroom(Request $request, Classroom $classroom): AnonymousResourceCollection
    {
        Gate::authorize('view', $classroom);

        $query = $classroom->assignments()->summary();

        if (! $classroom->isTaughtBy($request->user())) {
            $query->where('status', AssignmentStatus::Published);
        }

        return $this->collection($query);
    }

    /**
     * Mes devoirs publiés, dans toutes les classes dont je suis membre.
     */
    public function index(IndexAssignmentsRequest $request): AnonymousResourceCollection
    {
        $query = Assignment::summary()
            ->where('status', AssignmentStatus::Published)
            ->whereHas('classroom.members', fn ($m) => $m->whereKey($request->user()->id));

        if ($classroomId = $request->validated('classroom')) {
            $query->where('classroom_id', $classroomId);
        }

        return $this->collection($query);
    }

    public function store(StoreAssignmentRequest $request, Classroom $classroom, CreateAssignment $create): JsonResponse
    {
        Gate::authorize('create', [Assignment::class, $classroom]);

        $assignment = $create->handle($classroom, $request->assignmentData());

        return $this->detailed($assignment)->response()->setStatusCode(201);
    }

    public function show(Assignment $assignment): AssignmentResource
    {
        Gate::authorize('view', $assignment);

        return $this->detailed($assignment);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment, UpdateAssignment $update): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        return $this->detailed($update->handle($assignment, $request->assignmentData()));
    }

    public function publish(Assignment $assignment, ChangeAssignmentState $state): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        return $this->detailed($state->publish($assignment));
    }

    public function unpublish(Assignment $assignment, ChangeAssignmentState $state): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        return $this->detailed($state->unpublish($assignment));
    }

    public function releaseSolution(Assignment $assignment, ChangeAssignmentState $state): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        return $this->detailed($state->releaseSolution($assignment));
    }

    public function withholdSolution(Assignment $assignment, ChangeAssignmentState $state): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        return $this->detailed($state->withholdSolution($assignment));
    }

    public function destroy(Assignment $assignment): Response
    {
        Gate::authorize('manage', $assignment);

        $assignment->delete();

        return response()->noContent();
    }

    private function detailed(Assignment $assignment): AssignmentResource
    {
        $assignment->loadMissing('classroom');

        return (new AssignmentResource($assignment))->detailed();
    }

    /**
     * @param  Builder<Assignment>|HasMany<Assignment, Classroom>  $query
     */
    private function collection($query): AnonymousResourceCollection
    {
        return AssignmentResource::collection(
            $query->with('classroom')
                ->orderByRaw('assignments.due_at IS NULL')
                ->orderBy('assignments.due_at')
                ->orderByDesc('assignments.created_at')
                ->get()
        );
    }
}
