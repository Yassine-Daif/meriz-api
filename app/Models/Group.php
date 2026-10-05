<?php

namespace App\Models;

use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groupe d'élèves : un espace de travail commun, sans prof ni note.
 *
 * Son créateur en est l'administrateur. creator_id et join_code sont absents
 * de Fillable : le créateur vient du jeton, le code du serveur.
 */
#[Fillable(['name'])]
#[Hidden(['join_code'])]
class Group extends Model
{
    /** @use HasFactory<GroupFactory> */
    use HasFactory, HasUlids;

    protected $table = 'work_groups';

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_group_user', 'group_id', 'user_id')
            ->as('membership')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'group_id');
    }

    /**
     * Groupes visibles : ceux qu'on a créés et ceux dont on est membre.
     *
     * @param  Builder<Group>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('creator_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    public function isAdministeredBy(User $user): bool
    {
        return $this->creator_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }
}
