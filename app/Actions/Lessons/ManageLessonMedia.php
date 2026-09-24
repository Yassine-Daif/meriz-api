<?php

namespace App\Actions\Lessons;

use App\Enums\MediaKind;
use App\Models\Lesson;
use App\Models\LessonMedium;
use App\Rules\UploadedMedia;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Dépôt et retrait des médias d'un cours, sur le disque privé.
 *
 * Le genre et l'extension viennent du contenu réel du fichier, jamais du nom
 * envoyé. Le nom d'origine n'est gardé que pour l'affichage.
 */
class ManageLessonMedia
{
    public const QUOTA_MESSAGE = 'Nombre maximal de médias atteint pour ce cours.';

    /**
     * @throws ValidationException
     */
    public function store(Lesson $lesson, UploadedFile $file): LessonMedium
    {
        if ($lesson->media()->count() >= config('lessons.max_media_per_lesson')) {
            throw ValidationException::withMessages(['file' => self::QUOTA_MESSAGE]);
        }

        $mime = UploadedMedia::detectMime($file->getRealPath());
        $kind = MediaKind::fromMime($mime);
        $extension = $kind->mimes()[$mime];

        $path = $this->disk()->putFileAs(
            $lesson->mediaDirectory(),
            $file,
            Str::random(40).'.'.$extension,
        );

        $medium = new LessonMedium;
        $medium->forceFill([
            'lesson_id' => $lesson->id,
            'kind' => $kind,
            'path' => $path,
            'mime' => $mime,
            'size' => $file->getSize(),
            'name' => $this->safeName($file, $extension),
        ])->save();

        return $medium;
    }

    public function delete(LessonMedium $medium): void
    {
        $this->disk()->delete($medium->path);
        $medium->delete();
    }

    /**
     * Nom d'affichage nettoyé : ni chemin, ni caractères douteux.
     */
    private function safeName(UploadedFile $file, string $extension): string
    {
        $base = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = Str::of($base)->basename()->limit(100, '')->trim()->value();
        $base = preg_replace('/[^\p{L}\p{N} ._-]+/u', '', $base) ?: 'media';

        return $base.'.'.$extension;
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('lessons.disk'));
    }
}
