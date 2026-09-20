<?php

namespace App\Http\Controllers;

use App\Actions\Submissions\SubmitWork;
use App\Http\Requests\Submissions\StoreSubmissionRequest;
use App\Http\Resources\SubmissionResource;
use App\Models\Assignment;
use App\Models\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * {assignment} et {submission} ne sont résolus que parmi ce que
 * l'utilisateur peut voir (voir AppServiceProvider). Les Policies revérifient.
 */
class SubmissionController extends Controller
{
    /**
     * Remet ou met à jour son propre rendu.
     */
    public function store(StoreSubmissionRequest $request, Assignment $assignment, SubmitWork $submit): JsonResponse
    {
        Gate::authorize('submit', [Submission::class, $assignment]);

        ['submission' => $submission, 'created' => $created] = $submit->handle(
            $request->user(),
            $assignment,
            $request->validated('content'),
        );

        return $this->detailed($submission)
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    /**
     * Son propre rendu pour ce devoir.
     */
    public function mine(Request $request, Assignment $assignment): SubmissionResource
    {
        $submission = $assignment->submissions()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        Gate::authorize('view', $submission);

        return $this->detailed($submission);
    }

    /**
     * Tous les rendus d'un devoir : son prof seulement.
     */
    public function index(Assignment $assignment): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', [Submission::class, $assignment]);

        $submissions = $assignment->submissions()
            ->summary()
            ->with('student')
            ->orderByDesc('submitted_at')
            ->get()
            ->each->setRelation('assignment', $assignment);

        return SubmissionResource::collection($submissions);
    }

    public function show(Submission $submission): SubmissionResource
    {
        Gate::authorize('view', $submission);

        return $this->detailed($submission);
    }

    private function detailed(Submission $submission): SubmissionResource
    {
        $submission->loadMissing(['student', 'assignment']);

        return (new SubmissionResource($submission))->detailed();
    }
}
