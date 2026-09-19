<?php

namespace App\Actions\Assignments;

use App\Models\Assignment;
use App\Rules\ImageContent;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Dépôt et retrait de l'image d'un devoir, sur le disque privé.
 *
 * Le nom du fichier est aléatoire et son extension vient du type détecté
 * sur le contenu, jamais du nom envoyé par le client.
 */
class ManageAssignmentImage
{
    public function store(Assignment $assignment, UploadedFile $file): Assignment
    {
        // Type relu sur le contenu, déjà validé par ImageContent.
        $mime = ImageContent::detectMime($file->getRealPath());
        $extension = config('assignments.image_mimes')[$mime];
        $previous = $assignment->image_path;

        $path = $this->disk()->putFileAs(
            $assignment->imageDirectory(),
            $file,
            Str::random(40).'.'.$extension,
        );

        $assignment->forceFill([
            'image_path' => $path,
            'image_mime' => $mime,
            'image_size' => $file->getSize(),
        ])->save();

        if ($previous && $previous !== $path) {
            $this->disk()->delete($previous);
        }

        return $assignment;
    }

    public function delete(Assignment $assignment): Assignment
    {
        if ($assignment->image_path) {
            $this->disk()->delete($assignment->image_path);
        }

        $assignment->forceFill([
            'image_path' => null,
            'image_mime' => null,
            'image_size' => null,
        ])->save();

        return $assignment;
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('assignments.image_disk'));
    }
}
