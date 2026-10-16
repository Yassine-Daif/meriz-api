<?php

namespace App\Policies\Concerns;

use Closure;
use Illuminate\Auth\Access\Response;

/**
 * Les quatre formes de réponse des Policies, écrites une fois.
 *
 * Tout le cloisonnement du serveur tient dans la distinction entre deux
 * refus :
 *
 * - **refus caché** : `denyAsNotFound()`, rendu en 404 identique à celui
 *   d'une ressource inexistante. On ne révèle pas qu'elle existe.
 * - **refus explicite** : `deny($message)`, rendu en 403 avec sa raison.
 *   Réservé aux cas où la personne **sait déjà** que la ressource existe —
 *   un membre de la classe, par exemple. Lui répondre 404 serait mentir
 *   sans rien protéger.
 *
 * D'où la table de vérité, la même dans les sept Policies :
 *
 * | Situation                               | Réponse              |
 * |-----------------------------------------|----------------------|
 * | Autorisé                                | `allow()`            |
 * | Refusé, mais il sait que ça existe      | `deny($message)`     |
 * | Refusé, et il n'en sait rien            | `denyAsNotFound()`   |
 *
 * `reserve()` prend une **fermeture** pour la deuxième condition, et ce
 * n'est pas un détail de style : `hasMember()` et les relations d'un rendu
 * font des requêtes. Les évaluer avant de savoir si la première branche
 * suffit ajouterait une requête par autorisation, et trois tests comptent
 * les requêtes.
 */
trait DecidesAccess
{
    /**
     * Visible ou invisible : il n'y a pas de milieu.
     */
    protected function allowOrHide(bool $visible): Response
    {
        return $visible ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Autorisé, ou refusé avec sa raison. Pour les cas où l'existence de la
     * ressource n'est pas un secret.
     */
    protected function allowOrDeny(bool $allowed, string $message): Response
    {
        return $allowed ? Response::allow() : Response::deny($message);
    }

    /**
     * Les deux refus, selon que la personne sait ou non que la ressource
     * existe.
     */
    protected function denyOrHide(bool $knowsItExists, string $message): Response
    {
        return $knowsItExists ? Response::deny($message) : Response::denyAsNotFound();
    }

    /**
     * Les trois branches, dans cet ordre.
     *
     * @param  Closure(): bool  $knowsItExists  Évaluée seulement si le
     *                                          premier test échoue.
     */
    protected function reserve(bool $allowed, Closure $knowsItExists, string $message): Response
    {
        return $allowed
            ? Response::allow()
            : $this->denyOrHide($knowsItExists(), $message);
    }
}
