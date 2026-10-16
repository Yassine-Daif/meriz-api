<?php

namespace App\Routing;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Comment;
use App\Models\Document;
use App\Models\Group;
use App\Models\Lesson;
use App\Models\LessonMedium;
use App\Models\Submission;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * La première barrière du cloisonnement : un identifiant d'URL n'est jamais
 * résolu dans toute la table, mais seulement parmi ce que la personne peut
 * atteindre.
 *
 * Conséquence voulue : la ressource d'autrui lève la **même** exception
 * qu'une ressource inexistante, et le rendu uniforme de bootstrap/app.php en
 * fait le même 404. Rien ne distingue « ça n'existe pas » de « ce n'est pas
 * à toi ».
 *
 * La Policy revérifie ensuite, systématiquement : cette couche-ci ne la
 * remplace pas, elle la double. Les deux se recouvrent exprès, et c'est
 * mesurable : retirer `visibleTo` d'ici laisse la suite de tests au vert,
 * parce que les Policies rattrapent tous les cas. Ce n'est pas une raison
 * de le retirer — une requête qui ne charge jamais la ligne d'autrui est
 * plus sûre qu'une requête qui la charge puis la refuse.
 *
 * Deux familles, et deux seulement :
 *
 * 1. `visibleToUser` — résolu dans la portée `visibleTo` du modèle, avec
 *    l'utilisateur du jeton.
 * 2. `childOf` — résolu parmi les enfants d'une ressource **déjà résolue**
 *    par la liaison précédente, donc déjà filtrée.
 */
class ScopedRouteBindings
{
    /**
     * Les deux formats d'identifiant du serveur : ULID pour les ressources
     * métier, entier pour les comptes.
     *
     * Ce contrôle de forme n'est pas un garde-fou de cloisonnement : un
     * identifiant mal formé ne trouverait rien de toute façon, et donnerait
     * le même 404. Il sert à autre chose — en PostgreSQL, comparer une
     * chaîne non numérique à une colonne entière lève une erreur, donc un
     * 500 au lieu d'un 404. Le contrôle garde la réponse stable quelle que
     * soit la base. Ne pas le retirer en le croyant inutile.
     */
    private const ULID = 'ulid';

    private const NUMERIC = 'numeric';

    public function register(): void
    {
        // Famille 1. Chaque modèle porte sa propre règle de visibilité dans
        // sa portée visibleTo, et c'est là qu'elle se lit.
        $this->visibleToUser('document', Document::class);
        $this->visibleToUser('comment', Comment::class);
        $this->visibleToUser('group', Group::class);
        $this->visibleToUser('classroom', Classroom::class);
        $this->visibleToUser('assignment', Assignment::class);
        $this->visibleToUser('lesson', Lesson::class);
        $this->visibleToUser('submission', Submission::class);

        // Famille 2. Le parent est déjà résolu, donc déjà filtré : chercher
        // l'enfant chez lui suffit à le cloisonner.
        $this->childOf('member', 'classroom', Classroom::class, User::class,
            fn (Classroom $classroom) => $classroom->members(), self::NUMERIC);

        $this->childOf('participant', 'group', Group::class, User::class,
            fn (Group $group) => $group->members(), self::NUMERIC);

        // On n'observe que ses propres élèves : ceux de la classe du devoir.
        $this->childOf('student', 'assignment', Assignment::class, User::class,
            fn (Assignment $assignment) => $assignment->classroom->members(), self::NUMERIC);

        $this->childOf('medium', 'lesson', Lesson::class, LessonMedium::class,
            fn (Lesson $lesson) => $lesson->media(), self::ULID);
    }

    /**
     * Résout {$parameter} parmi ce que l'utilisateur du jeton peut atteindre.
     *
     * @param  class-string<Model>  $model
     */
    private function visibleToUser(string $parameter, string $model): void
    {
        Route::bind($parameter, function (string $value) use ($model) {
            $user = request()->user();

            if (! $user || ! Str::isUlid($value)) {
                throw $this->missing($model);
            }

            /** @var Builder<Model> $query */
            $query = $model::visibleTo($user);

            return $query->whereKey($value)->firstOrFail();
        });
    }

    /**
     * Résout {$parameter} parmi les enfants de {$parentParameter}, déjà
     * résolu par sa propre liaison.
     *
     * @param  class-string<Model>  $parentModel
     * @param  class-string<Model>  $model
     * @param  Closure(Model): Relation<Model, Model, *>  $children
     */
    private function childOf(
        string $parameter,
        string $parentParameter,
        string $parentModel,
        string $model,
        Closure $children,
        string $idFormat,
    ): void {
        Route::bind($parameter, function (string $value, RoutingRoute $route) use (
            $parentParameter, $parentModel, $model, $children, $idFormat
        ) {
            $parent = $route->parameter($parentParameter);

            if (! $parent instanceof $parentModel || ! $this->wellFormed($value, $idFormat)) {
                throw $this->missing($model);
            }

            return $children($parent)->whereKey($this->key($value, $idFormat))->firstOrFail();
        });
    }

    private function wellFormed(string $value, string $format): bool
    {
        return $format === self::ULID ? Str::isUlid($value) : ctype_digit($value);
    }

    private function key(string $value, string $format): string|int
    {
        return $format === self::ULID ? $value : (int) $value;
    }

    /**
     * La même exception qu'un identifiant inexistant, toujours.
     *
     * @param  class-string<Model>  $model
     */
    private function missing(string $model): ModelNotFoundException
    {
        return (new ModelNotFoundException)->setModel($model);
    }
}
