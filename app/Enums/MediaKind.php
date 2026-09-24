<?php

namespace App\Enums;

enum MediaKind: string
{
    case Image = 'image';
    case Audio = 'audio';

    /**
     * Types acceptés pour ce genre de média, indexés par type MIME réel.
     *
     * @return array<string, string>
     */
    public function mimes(): array
    {
        return match ($this) {
            self::Image => config('lessons.image_mimes'),
            self::Audio => config('lessons.audio_mimes'),
        };
    }

    public function maxKilobytes(): int
    {
        return match ($this) {
            self::Image => config('lessons.max_image_kb'),
            self::Audio => config('lessons.max_audio_kb'),
        };
    }

    /**
     * Genre correspondant à un type MIME, ou null s'il n'est pas accepté.
     */
    public static function fromMime(?string $mime): ?self
    {
        foreach (self::cases() as $kind) {
            if ($mime !== null && array_key_exists($mime, $kind->mimes())) {
                return $kind;
            }
        }

        return null;
    }
}
