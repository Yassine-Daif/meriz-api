<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use Database\Factories\AssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * Devoir d'une classe.
 *
 * classroom_id, le statut, les dates de publication et de libération et les
 * champs d'image sont absents de Fillable : ils ne s'écrivent que par les
 * actions dédiées. Le corrigé, la base et le chemin de l'image sont cachés à
 * la sérialisation : seule AssignmentResource décide de ce qui sort.
 */
#[Fillable(['title', 'instructions', 'type', 'due_at', 'base_content', 'solution_content'])]
#[Hidden(['solution_content', 'base_content', 'image_path'])]
class Assignment extends Model
{
    /** @use HasFactory<AssignmentFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Assignment $assignment) {
            if ($assignment->image_path) {
                Storage::disk(config('assignments.image_disk'))->delete($assignment->image_path);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AssignmentType::class,
            'status' => AssignmentStatus::class,
            'due_at' => 'datetime',
            'published_at' => 'datetime',
            'solution_released_at' => 'datetime',
            'image_size' => 'integer',
        ];
    }

    /**
     * Documents copiés depuis la base de ce devoir.
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * @return HasMany<Submission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * Devoirs visibles : tous ceux des classes que l'utilisateur enseigne,
     * et seulement les devoirs publiés des classes dont il est membre.
     *
     * @param  Builder<Assignment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->whereHas('classroom', fn (Builder $c) => $c->where('teacher_id', $user->id))
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('status', AssignmentStatus::Published)
                        ->whereHas('classroom.members', fn (Builder $m) => $m->whereKey($user->id));
                });
        });
    }

    public function isPublished(): bool
    {
        return $this->status === AssignmentStatus::Published;
    }

    public function solutionReleased(): bool
    {
        return $this->solution_released_at !== null;
    }

    public function hasImage(): bool
    {
        return $this->image_path !== null;
    }

    /**
     * Les listes ne chargent pas les contenus : elles sélectionnent à la
     * place les indicateurs has_base_flag et has_solution_flag (voir
     * scopeSummary).
     */
    public function hasBase(): bool
    {
        return array_key_exists('has_base_flag', $this->attributes)
            ? (bool) $this->attributes['has_base_flag']
            : $this->base_content !== null;
    }

    public function hasSolution(): bool
    {
        return array_key_exists('has_solution_flag', $this->attributes)
            ? (bool) $this->attributes['has_solution_flag']
            : $this->solution_content !== null;
    }

    /**
     * Colonnes d'une liste : ni consigne, ni base, ni corrigé.
     *
     * @param  Builder<Assignment>  $query
     */
    public function scopeSummary(Builder $query): void
    {
        $query->select([
            'assignments.id', 'assignments.classroom_id', 'assignments.title', 'assignments.type',
            'assignments.due_at', 'assignments.status', 'assignments.published_at',
            'assignments.solution_released_at', 'assignments.image_path',
            'assignments.created_at', 'assignments.updated_at',
        ])->selectRaw('assignments.base_content IS NOT NULL AS has_base_flag')
            ->selectRaw('assignments.solution_content IS NOT NULL AS has_solution_flag');
    }

    public function imageDirectory(): string
    {
        return 'assignments/'.$this->classroom_id.'/'.$this->id;
    }
}
