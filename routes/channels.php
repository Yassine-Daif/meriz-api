<?php

use App\Actions\Assignments\ObserveWork;
use App\Http\Resources\CollaboratorResource;
use App\Models\Assignment;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

/**
 * Canal d'observation : un par couple devoir + élève.
 *
 * L'autorisation n'invente rien. Elle s'appuie sur AssignmentPolicy::observeLive,
 * la règle déjà en place et déjà testée : seul le prof du devoir, et seulement
 * si le suivi en direct est activé. Un élève, un autre prof et un non-membre
 * sont donc refusés ici comme ils le sont en HTTP.
 */
/**
 * Canal de co-édition : un canal de présence par document.
 *
 * L'autorisation passe par DocumentPolicy::collaborate, qui compose les
 * règles existantes : l'élève propriétaire, et le prof du devoir lié quand le
 * suivi est activé. Le canal est prêt à accueillir d'autres participants pour
 * le travail de groupe, l'ouverture se fera dans cette seule règle.
 *
 * Les mises à jour Yjs et les curseurs circulent en évènements de client sur
 * ce canal, de pair à pair. Le serveur ne les lit pas.
 */
Broadcast::channel('documents.{documentId}', function (User $user, string $documentId) {
    $document = Document::find($documentId);

    if (! $document || Gate::forUser($user)->denies('collaborate', $document)) {
        // null : refus, sur un canal de présence comme sur un canal privé.
        return null;
    }

    // Transparence : un prof qui rejoint laisse la même trace de lecture
    // qu'en observation simple. L'élève voit donc qu'on est venu.
    if ($document->user_id !== $user->id && $document->assignment !== null) {
        app(ObserveWork::class)->markObserved($document->assignment, $document->user);
    }

    // Identité d'affichage seulement : nom, prénom, rôle, couleurs.
    return (new CollaboratorResource($user))->resolve();
});

Broadcast::channel('assignments.{assignmentId}.work.{studentId}', function (User $user, string $assignmentId, string $studentId) {
    $assignment = Assignment::find($assignmentId);

    if (! $assignment || Gate::forUser($user)->denies('observeLive', $assignment)) {
        return false;
    }

    // La cible doit être un élève de cette classe, comme la liaison {student}.
    $student = $assignment->classroom->members()->whereKey($studentId)->first();

    if (! $student) {
        return false;
    }

    // Transparence : s'abonner, c'est commencer à observer. L'élève verra
    // l'heure de cette lecture sur son document.
    app(ObserveWork::class)->markObserved($assignment, $student);

    return true;
});
