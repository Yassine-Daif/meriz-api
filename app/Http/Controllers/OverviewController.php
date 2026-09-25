<?php

namespace App\Http\Controllers;

use App\Enums\AssignmentStatus;
use App\Enums\SubmissionStatus;
use App\Http\Requests\Overview\ToGradeRequest;
use App\Http\Resources\StudentAssignmentResource;
use App\Http\Resources\ToGradeResource;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Vues d'ensemble, en lecture seule : chacune répond en un appel et en un
 * nombre de requêtes constant, quel que soit le nombre de classes.
 *
 * Les deux partent de l'utilisateur du jeton : aucun identifiant de classe,
 * de devoir ni d'élève n'est accepté du client.
 */
class OverviewController extends Controller
{
    /**
     * Les rendus remis et pas encore notés, sur tous les devoirs des classes
     * dont l'utilisateur est le prof.
     */
    public function toGrade(ToGradeRequest $request): AnonymousResourceCollection
    {
        // Pas de ressource unique à autoriser ici : la visibilité est portée
        // par la requête elle-même, restreinte aux classes du prof du jeton.
        // C'est la même règle que SubmissionPolicy::viewAny, appliquée en gros.
        $teacherId = $request->user()->id;

        $submissions = Submission::summary()
            ->where('status', SubmissionStatus::Submitted)
            ->whereHas(
                'assignment.classroom',
                fn (Builder $classroom) => $classroom->where('teacher_id', $teacherId),
            )
            ->with([
                'student',
                'assignment' => fn ($query) => $query
                    ->select(['id', 'classroom_id', 'title', 'type', 'due_at'])
                    ->with('classroom:id,name'),
            ])
            // Les plus anciens à corriger d'abord.
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->paginate($request->perPage())
            ->withQueryString();

        return ToGradeResource::collection($submissions);
    }

    /**
     * Les devoirs publiés de toutes les classes de l'élève, avec l'état de
     * son travail.
     */
    public function myAssignments(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->id;

        $assignments = Assignment::summary()
            ->where('status', AssignmentStatus::Published)
            ->whereHas(
                'classroom.members',
                fn (Builder $members) => $members->whereKey($userId),
            )
            // Le travail en cours le plus récent, en sous-requête : pas de
            // requête par devoir.
            ->addSelect(['my_document_id' => Document::select('id')
                ->whereColumn('assignment_id', 'assignments.id')
                ->where('user_id', $userId)
                ->orderByDesc('updated_at')
                ->limit(1),
            ])
            ->with([
                'classroom:id,name',
                // Seulement son propre rendu, et sans son contenu.
                'submissions' => fn ($query) => $query->summary()->where('user_id', $userId),
            ])
            // Par échéance, les devoirs sans date limite en dernier.
            ->orderByRaw('assignments.due_at IS NULL')
            ->orderBy('assignments.due_at')
            ->orderByDesc('assignments.published_at')
            ->get();

        return StudentAssignmentResource::collection($assignments);
    }
}
