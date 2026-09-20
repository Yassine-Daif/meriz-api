<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Database\Factories\SubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Travail rendu par un élève pour un devoir.
 *
 * Seul content est remplissable : l'auteur, le devoir, l'état, la note et
 * les dates ne s'écrivent que par les actions dédiées.
 */
#[Fillable(['content'])]
class Submission extends Model
{
    /** @use HasFactory<SubmissionFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'submitted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Assignment, $this>
     */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Rendus visibles : les siens, et tous ceux des devoirs des classes que
     * l'utilisateur enseigne.
     *
     * @param  Builder<Submission>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->where('user_id', $user->id)
                ->orWhereHas(
                    'assignment.classroom',
                    fn (Builder $classroom) => $classroom->where('teacher_id', $user->id),
                );
        });
    }

    /**
     * Colonnes d'une liste : sans le contenu du travail.
     *
     * @param  Builder<Submission>  $query
     */
    public function scopeSummary(Builder $query): void
    {
        $query->select([
            'submissions.id', 'submissions.assignment_id', 'submissions.user_id',
            'submissions.submitted_at', 'submissions.status', 'submissions.grade',
            'submissions.feedback', 'submissions.graded_at',
            'submissions.created_at', 'submissions.updated_at',
        ]);
    }

    public function isGraded(): bool
    {
        return $this->status === SubmissionStatus::Graded;
    }

    /**
     * En retard si la remise est postérieure à la date limite du devoir.
     * Sans date limite, jamais en retard.
     */
    public function isLate(): bool
    {
        $dueAt = $this->assignment->due_at;

        return $dueAt !== null && $this->submitted_at !== null && $this->submitted_at->greaterThan($dueAt);
    }
}
