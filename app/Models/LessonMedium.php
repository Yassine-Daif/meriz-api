<?php

namespace App\Models;

use App\Enums\MediaKind;
use Database\Factories\LessonMediumFactory;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Média d'un cours : une image ou un audio, sur le disque privé.
 *
 * Rien n'est remplissable : tout est écrit par ManageLessonMedia, à partir
 * du contenu réel du fichier. Le chemin de stockage n'est jamais exposé.
 */
#[Hidden(['path'])]
class LessonMedium extends Model
{
    /** @use HasFactory<LessonMediumFactory> */
    use HasFactory, HasUlids;

    protected $table = 'lesson_media';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'size' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isImage(): bool
    {
        return $this->kind === MediaKind::Image;
    }
}
