<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\Group;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Un document est soit personnel, soit partagé dans un groupe.
 *
 * Personnel : seul son propriétaire y touche. Partagé : tous les membres du
 * groupe le lisent et le modifient, c'est un espace commun. Le refus prend la
 * forme d'un 404 : on ne révèle pas qu'un document existe.
 */
class DocumentPolicy
{
    public const DELETE_MESSAGE = 'Seul l\'auteur du document ou le créateur du groupe peut le supprimer.';

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, Document $document): Response
    {
        return $this->reachable($user, $document)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Document $document): Response
    {
        return $this->reachable($user, $document)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Supprimer : celui qui a créé le document, ou le créateur du groupe.
     * Un membre ne supprime pas le travail d'un autre.
     */
    public function delete(User $user, Document $document): Response
    {
        $group = $document->group;

        // Document personnel : son propriétaire, et personne d'autre.
        if ($group === null) {
            return $document->user_id === $user->id
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        // Quitter le groupe, ou en être retiré, ferme l'accès : même pour qui
        // a créé le document, il appartient à l'espace commun.
        if (! $this->belongsToGroup($user, $group)) {
            return Response::denyAsNotFound();
        }

        if ($document->user_id === $user->id || $group->isAdministeredBy($user)) {
            return Response::allow();
        }

        // Un membre du groupe sait que le document existe : refus clair.
        return Response::deny(self::DELETE_MESSAGE);
    }

    /**
     * Rejoindre la session de co-édition d'un document.
     *
     * Cette règle n'invente rien : elle compose celles qui existent déjà.
     * C'est le seul endroit où l'on ouvre la co-édition à de nouveaux
     * participants.
     */
    public function collaborate(User $user, Document $document): bool
    {
        // 0. Document partagé : seule l'appartenance au groupe compte, même
        //    pour qui l'a créé. Être retiré du groupe ferme l'accès.
        if ($document->isShared()) {
            return $this->belongsToGroup($user, $document->group);
        }

        // 1. L'élève, sur son propre travail.
        if ($document->user_id === $user->id) {
            return true;
        }

        // 2. Le prof du devoir lié, suivi activé : AssignmentPolicy::observeLive,
        //    la règle d'observation déjà en place et déjà testée.
        $assignment = $document->assignment;

        if ($assignment !== null && Gate::forUser($user)->allows('observeLive', $assignment)) {
            return true;
        }

        // 3. Point d'extension : d'autres participants s'ajouteront ici.
        return false;
    }

    /**
     * Commenter un travail, et voir ses commentaires.
     *
     * Comme `collaborate`, cette règle n'invente rien : elle compose celles
     * qui existent. Une différence voulue : elle ne dépend **pas** du suivi
     * en direct, car commenter n'est pas observer. Un prof corrige quand il
     * veut, l'élève lui répond.
     */
    public function discuss(User $user, Document $document): bool
    {
        // 1. L'élève, sur son propre travail.
        if ($document->user_id === $user->id) {
            return true;
        }

        // 2. Le prof du devoir lié : AssignmentPolicy::manage dit déjà
        //    « le prof de cette classe ».
        $assignment = $document->assignment;

        if ($assignment !== null && Gate::forUser($user)->allows('manage', $assignment)) {
            return true;
        }

        // 3. Les membres du groupe, sur un document partagé.
        return $this->belongsToGroup($user, $document->group);
    }

    /**
     * Atteignable en lecture et en écriture.
     *
     * Un document partagé relève du groupe : tous ses membres y touchent, et
     * un ancien membre n'y touche plus, même s'il l'avait créé. Un document
     * personnel ne regarde que son propriétaire.
     */
    private function reachable(User $user, Document $document): bool
    {
        return $document->isShared()
            ? $this->belongsToGroup($user, $document->group)
            : $document->user_id === $user->id;
    }

    private function belongsToGroup(User $user, ?Group $group): bool
    {
        return $group !== null && ($group->isAdministeredBy($user) || $group->hasMember($user));
    }
}
