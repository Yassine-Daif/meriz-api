<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Models\Document;
use App\Models\Submission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Les élèves d'un devoir et l'état de leur travail, sans son contenu.
 *
 * Tout passe par des sous-requêtes corrélées : le nombre de requêtes est le
 * même pour deux élèves que pour trente, et un test l'exige explicitement.
 * Ne pas remplacer par une boucle sur les membres.
 *
 * Les colonnes de l'élève sont choisies une par une parce que la ligne part
 * ensuite dans un profil public : pas d'email de connexion ici.
 */
class ListLiveWorkers
{
    /**
     * @return Collection<int, Model>
     */
    public function handle(Assignment $assignment): Collection
    {
        $work = fn (string $column) => Document::select($column)
            ->whereColumn('documents.user_id', 'users.id')
            ->where('documents.assignment_id', $assignment->id)
            ->orderByDesc('documents.updated_at')
            ->limit(1);

        return $assignment->classroom->members()
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
    }
}
