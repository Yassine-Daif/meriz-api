<?php

use App\Actions\Assignments\ObserveWork;
use App\Models\Assignment;
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
