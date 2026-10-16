<?php

namespace App\Actions\Overview;

use App\Enums\SubmissionStatus;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les rendus remis et pas encore notés, sur tous les devoirs des classes
 * dont la personne est le prof.
 *
 * La visibilité est portée par la requête elle-même, restreinte aux classes
 * du prof du jeton : c'est la même règle que SubmissionPolicy::viewAny,
 * appliquée en gros. Aucun identifiant ne vient du client.
 *
 * Le nombre de requêtes ne dépend pas du nombre de classes ni de rendus :
 * un test le vérifie, ne pas remplacer les chargements anticipés par des
 * accès en boucle.
 */
class ListSubmissionsToGrade
{
    /**
     * @return LengthAwarePaginator<int, Submission>
     */
    public function handle(User $teacher, int $perPage): LengthAwarePaginator
    {
        return Submission::summary()
            ->where('status', SubmissionStatus::Submitted)
            ->whereHas(
                'assignment.classroom',
                fn (Builder $classroom) => $classroom->where('teacher_id', $teacher->id),
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
            ->paginate($perPage)
            ->withQueryString();
    }
}
