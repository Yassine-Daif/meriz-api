<?php

namespace App\Actions\Overview;

use App\Enums\AssignmentStatus;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Les devoirs publiés de toutes les classes de l'élève, avec l'état de son
 * travail.
 *
 * Un brouillon n'en fait jamais partie, et le travail en cours est repris
 * par une sous-requête : le nombre de requêtes ne dépend pas du nombre de
 * devoirs, et un test le vérifie.
 */
class ListStudentAssignments
{
    /**
     * @return Collection<int, Assignment>
     */
    public function handle(User $student): Collection
    {
        return Assignment::summary()
            ->where('status', AssignmentStatus::Published)
            ->whereHas(
                'classroom.members',
                fn (Builder $members) => $members->whereKey($student->id),
            )
            // Le travail en cours le plus récent, en sous-requête : pas de
            // requête par devoir.
            ->addSelect(['my_document_id' => Document::select('id')
                ->whereColumn('assignment_id', 'assignments.id')
                ->where('user_id', $student->id)
                ->orderByDesc('updated_at')
                ->limit(1),
            ])
            ->with([
                'classroom:id,name',
                // Seulement son propre rendu, et sans son contenu.
                'submissions' => fn ($query) => $query->summary()->where('user_id', $student->id),
            ])
            // Par échéance, les devoirs sans date limite en dernier.
            ->orderByRaw('assignments.due_at IS NULL')
            ->orderBy('assignments.due_at')
            ->orderByDesc('assignments.published_at')
            ->get();
    }
}
