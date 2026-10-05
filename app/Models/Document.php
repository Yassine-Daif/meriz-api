<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Document d'un utilisateur.
 *
 * content est le fichier produit par l'application, gardé en texte brut :
 * aucun cast, pour le renvoyer octet pour octet. user_id et assignment_id
 * sont absents de Fillable : le propriétaire vient toujours de l'utilisateur
 * authentifié, et le rattachement à un devoir de l'action de copie.
 */
#[Fillable(['name', 'content'])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_observed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Devoir dont ce document est une copie de la base, s'il y en a un.
     *
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * Groupe auquel ce document appartient, s'il est partagé.
     *
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function isShared(): bool
    {
        return $this->group_id !== null;
    }

    /**
     * Documents strictement personnels, hors espaces de groupe.
     *
     * @param  Builder<Document>  $query
     */
    public function scopePersonal(Builder $query): void
    {
        $query->whereNull('group_id');
    }

    /**
     * Documents atteignables : les siens, et ceux des groupes dont on est
     * membre. La Policy décide ensuite de ce qu'on peut en faire.
     *
     * @param  Builder<Document>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas(
                    'group.members',
                    fn (Builder $members) => $members->whereKey($user->id),
                );
        });
    }
}
