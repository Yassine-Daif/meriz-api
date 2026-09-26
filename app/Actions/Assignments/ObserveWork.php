<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Models\Document;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Lit l'instantané du travail en cours d'un élève pour un devoir.
 *
 * Lecture seule sur le contenu. Le document est épinglé par le couple
 * devoir + élève : aucun identifiant ne vient du client, donc un document
 * personnel ou celui d'un autre devoir est hors de portée.
 */
class ObserveWork
{
    /**
     * @throws ModelNotFoundException si l'élève n'a pas commencé
     */
    public function handle(Assignment $assignment, User $student): Document
    {
        $document = Document::query()
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->orderByDesc('updated_at')
            ->firstOrFail();

        $observedAt = $this->stamp($document);
        $document->setAttribute('last_observed_at', $observedAt);

        return $document;
    }

    /**
     * Note une consultation sans lire le contenu : utilisé quand le prof
     * s'abonne au canal, et à chaque ping « j'observe toujours ».
     *
     * Renvoie null si l'élève n'a pas commencé.
     */
    public function markObserved(Assignment $assignment, User $student): ?CarbonInterface
    {
        $document = Document::query()
            ->select(['id'])
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $student->id)
            ->orderByDesc('updated_at')
            ->first();

        return $document === null ? null : $this->stamp($document);
    }

    /**
     * Transparence : on note la consultation. Un update direct, pour ne
     * toucher ni le contenu ni updated_at, qui sert de date d'activité.
     */
    private function stamp(Document $document): CarbonInterface
    {
        $observedAt = now();
        Document::whereKey($document->id)->toBase()->update(['last_observed_at' => $observedAt]);

        return $observedAt;
    }
}
