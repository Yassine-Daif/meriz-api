<?php

namespace App\Rules;

use Closure;
use finfo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Vérifie qu'un fichier envoyé est réellement du type annoncé, en lisant ses
 * octets : ni son nom, ni l'en-tête du client ne comptent.
 *
 * Le type détecté doit figurer dans la liste autorisée. Pour une image,
 * getimagesize doit en plus reconnaître une image du même type. Un HTML ou
 * un PHP déguisé en .png ou en .mp3, un SVG et un ZIP sont donc refusés.
 * Un vrai MP3 nommé .png reste accepté : c'est bien un média autorisé, et
 * il est enregistré sous son type réel.
 */
class UploadedMedia implements ValidationRule
{
    /**
     * @param  list<string>  $allowedMimes
     */
    public function __construct(
        private readonly array $allowedMimes,
        private readonly string $message,
        private readonly bool $mustBeImage = false,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail($this->message);

            return;
        }

        $mime = self::detectMime($value->getRealPath());

        if (! in_array($mime, $this->allowedMimes, true)) {
            $fail($this->message);

            return;
        }

        if ($this->mustBeImage || str_starts_with((string) $mime, 'image/')) {
            $info = @getimagesize($value->getRealPath());

            if ($info === false || $info['mime'] !== $mime) {
                $fail($this->message);
            }
        }
    }

    public static function detectMime(?string $path): ?string
    {
        if ($path === null || ! is_file($path)) {
            return null;
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);

        return $mime === false ? null : $mime;
    }
}
