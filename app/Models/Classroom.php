<?php

namespace App\Models;

use Database\Factories\ClassroomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Classe d'un prof.
 *
 * teacher_id et join_code sont absents de Fillable : le prof vient du jeton
 * et le code est généré par le serveur.
 */
#[Fillable(['name'])]
#[Hidden(['join_code'])]
class Classroom extends Model
{
    /** @use HasFactory<ClassroomFactory> */
    use HasFactory, HasUlids;

    protected static function booted(): void
    {
        // Les devoirs partent en cascade dans la base, sans événement :
        // on efface ici le dossier de leurs images.
        static::deleting(function (Classroom $classroom) {
            Storage::disk(config('assignments.image_disk'))->deleteDirectory('assignments/'.$classroom->id);
        });
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->as('membership')
            ->withTimestamps();
    }

    /**
     * Classes que l'utilisateur peut voir : celles qu'il enseigne et celles
     * dont il est membre.
     *
     * @param  Builder<Classroom>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('teacher_id', $user->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($user->id));
        });
    }

    public function isTaughtBy(User $user): bool
    {
        return $this->teacher_id === $user->id;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->id)->exists();
    }
}
