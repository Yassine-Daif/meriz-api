<?php

namespace App\Http\Controllers;

use App\Actions\Assignments\ChangeAssignmentState;
use App\Actions\Assignments\ObserveWork;
use App\Http\Resources\AssignmentResource;
use App\Http\Resources\LiveSnapshotResource;
use App\Http\Resources\LiveWorkerResource;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * Suivi en direct, consenti et en lecture seule.
 *
 * Le prof propriétaire ouvre le suivi sur un devoir, puis lit l'état du
 * travail de ses élèves. Rien n'est lisible tant que le drapeau est baissé,
 * et chaque lecture laisse une trace que l'élève voit.
 */
class LiveTrackingController extends Controller
{
    public function enable(Assignment $assignment, ChangeAssignmentState $state): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        return $this->assignment($state->enableLiveTracking($assignment));
    }

    public function disable(Assignment $assignment, ChangeAssignmentState $state): AssignmentResource
    {
        Gate::authorize('manage', $assignment);

        return $this->assignment($state->disableLiveTracking($assignment));
    }

    /**
     * Les élèves de la classe et l'état de leur travail, sans contenu.
     */
    public function index(Assignment $assignment): AnonymousResourceCollection
    {
        Gate::authorize('observeLive', $assignment);

        // Sous-requêtes : un nombre de requêtes constant quel que soit l'effectif.
        $work = fn (string $column) => Document::select($column)
            ->whereColumn('documents.user_id', 'users.id')
            ->where('documents.assignment_id', $assignment->id)
            ->orderByDesc('documents.updated_at')
            ->limit(1);

        $students = $assignment->classroom->members()
            ->select(['users.id', 'users.name', 'users.first_name', 'users.bio', 'users.bio_shared',
                'users.contact', 'users.contact_shared', 'users.avatar_bg', 'users.avatar_fg'])
            ->addSelect([
                'work_id' => $work('documents.id'),
                'work_updated_at' => $work('documents.updated_at'),
                'work_observed_at' => $work('documents.last_observed_at'),
                'submission_status' => Submission::select('status')
                    ->whereColumn('submissions.user_id', 'users.id')
                    ->where('submissions.assignment_id', $assignment->id)
                    ->limit(1),
            ])
            ->orderBy('users.name')
            ->orderBy('users.first_name')
            ->get();

        return LiveWorkerResource::collection($students);
    }

    /**
     * L'instantané du travail de cet élève pour ce devoir.
     */
    public function show(Assignment $assignment, User $student, ObserveWork $observe): LiveSnapshotResource
    {
        Gate::authorize('observeLive', $assignment);

        $document = $observe->handle($assignment, $student);
        $document->setRelation('user', $student);

        return new LiveSnapshotResource($document);
    }

    private function assignment(Assignment $assignment): AssignmentResource
    {
        $assignment->loadMissing('classroom');

        return (new AssignmentResource($assignment))->detailed();
    }
}
