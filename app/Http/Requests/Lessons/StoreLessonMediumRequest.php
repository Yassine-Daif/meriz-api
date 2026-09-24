<?php

namespace App\Http\Requests\Lessons;

use App\Enums\MediaKind;
use App\Rules\UploadedMedia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

/**
 * Images et audios seulement. Le type est lu dans les octets du fichier,
 * jamais dans son nom ni dans l'en-tête envoyé.
 */
class StoreLessonMediumRequest extends FormRequest
{
    public const MESSAGE = 'Seules les images (JPEG, PNG, WebP) et les pistes audio (MP3, OGG, WAV, M4A, WebM) sont acceptées.';

    public function authorize(): bool
    {
        Gate::authorize('manage', $this->route('lesson'));

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $allowed = [...array_keys(config('lessons.image_mimes')), ...array_keys(config('lessons.audio_mimes'))];

        return [
            'file' => [
                'bail',
                'required',
                // La taille dépend du genre réel du fichier : image ou audio.
                File::default()->max($this->maxKilobytes()),
                new UploadedMedia($allowed, self::MESSAGE),
            ],
        ];
    }

    private function maxKilobytes(): int
    {
        $kind = MediaKind::fromMime(UploadedMedia::detectMime(
            $this->file('file')?->getRealPath(),
        ));

        return $kind?->maxKilobytes() ?? config('lessons.max_image_kb');
    }
}
