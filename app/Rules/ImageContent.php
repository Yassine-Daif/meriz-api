<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Vérifie qu'un fichier est réellement une image d'un type autorisé, en
 * lisant ses octets : ni son nom, ni l'en-tête envoyé par le client ne
 * comptent. Le type détecté par finfo doit être autorisé, et getimagesize
 * doit reconnaître une image de ce même type.
 */
class ImageContent implements ValidationRule
{
    public const MESSAGE = 'Seules les images JPEG, PNG ou WebP sont acceptées.';

    /**
     * @param  list<string>  $allowedMimes
     */
    public function __construct(private readonly array $allowedMimes) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail(self::MESSAGE);

            return;
        }

        $mime = self::detectMime($value->getRealPath());
        $info = @getimagesize($value->getRealPath());

        if (! in_array($mime, $this->allowedMimes, true) || $info === false || $info['mime'] !== $mime) {
            $fail(self::MESSAGE);
        }
    }

    public static function detectMime(string $path): ?string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);

        return $mime === false ? null : $mime;
    }
}
