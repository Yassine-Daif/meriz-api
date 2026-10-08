<?php

namespace App\Models;

use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Commentaire sur un travail : général, ou posé à un endroit du schéma.
 *
 * Seul body est remplissable. L'auteur vient du jeton, le document de la
 * route, la position et l'état résolu des actions dédiées.
 */
#[Fillable(['body'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory, HasUlids;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position_x' => 'float',
            'position_y' => 'float',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function hasPosition(): bool
    {
        return $this->position_x !== null && $this->position_y !== null;
    }

    /**
     * Commentaires atteignables : ceux des travaux que l'utilisateur peut
     * commenter, c'est-à-dire les siens, ceux des devoirs dont il est le
     * prof, et ceux des groupes dont il est membre. Tout le reste donne un
     * 404 dès la liaison de route.
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('document', function (Builder $document) use ($user) {
            $document->where(function (Builder $document) use ($user) {
                $document
                    // Son propre travail.
                    ->where('user_id', $user->id)
                    // Le devoir dont il est le prof.
                    ->orWhereHas(
                        'assignment.classroom',
                        fn (Builder $classroom) => $classroom->where('teacher_id', $user->id),
                    )
                    // Un document partagé d'un de ses groupes.
                    ->orWhereHas(
                        'group.members',
                        fn (Builder $members) => $members->whereKey($user->id),
                    );
            });
        });
    }
}
