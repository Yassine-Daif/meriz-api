<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Image seulement : cas particulier de UploadedMedia, gardé pour les images
 * de devoirs.
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
        (new UploadedMedia($this->allowedMimes, self::MESSAGE, mustBeImage: true))
            ->validate($attribute, $value, $fail);
    }

    public static function detectMime(string $path): ?string
    {
        return UploadedMedia::detectMime($path);
    }
}
