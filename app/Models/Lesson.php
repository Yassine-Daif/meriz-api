<?php

namespace App\Models;

use App\Enums\LessonStatus;
use Database\Factories\LessonFactory;
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
 * Cours d'une classe : un titre et une page de blocs ordonnés.
 *
 * classroom_id, l'état et la date de publication sont absents de Fillable :
 * ils ne s'écrivent que par les actions dédiées. La page de blocs est cachée
 * à la sérialisation : seule LessonResource décide de ce qui sort.
 */
#[Fillable(['title', 'blocks'])]
#[Hidden(['blocks'])]
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory, HasUlids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Lesson $lesson) {
            Storage::disk(config('lessons.disk'))->deleteDirectory($lesson->mediaDirectory());
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => LessonStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Classroom, $this>
     */
    public function classroom(): BelongsTo
    {
        return $this->belongsTo(Classroom::class);
    }

    /**
     * @return HasMany<LessonMedium, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(LessonMedium::class);
    }

    /**
     * Cours visibles : tous ceux des classes enseignées, et seulement les
     * cours publiés des classes dont l'utilisateur est membre.
     *
     * @param  Builder<Lesson>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user) {
            $query->whereHas('classroom', fn (Builder $c) => $c->where('teacher_id', $user->id))
                ->orWhere(function (Builder $query) use ($user) {
                    $query->where('status', LessonStatus::Published)
                        ->whereHas('classroom.members', fn (Builder $m) => $m->whereKey($user->id));
                });
        });
    }

    /**
     * Colonnes d'une liste : sans la page de blocs.
     *
     * @param  Builder<Lesson>  $query
     */
    public function scopeSummary(Builder $query): void
    {
        $query->select([
            'lessons.id', 'lessons.classroom_id', 'lessons.title', 'lessons.status',
            'lessons.published_at', 'lessons.created_at', 'lessons.updated_at',
        ]);
    }

    public function isPublished(): bool
    {
        return $this->status === LessonStatus::Published;
    }

    public function mediaDirectory(): string
    {
        return 'lessons/'.$this->classroom_id.'/'.$this->id;
    }
}
