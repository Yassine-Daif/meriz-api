<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Couleur hex : #abc ou #aabbcc, insensible à la casse. Rien d'autre.
 */
class HexColor implements ValidationRule
{
    public const MESSAGE = 'La couleur doit être au format hexadécimal, par exemple #1e1b4b.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $value) !== 1) {
            $fail(self::MESSAGE);
        }
    }

    /**
     * Forme unique en base : minuscules, et #abc développé en #aabbcc.
     */
    public static function normalize(?string $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = strtolower(trim($value));

        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m) === 1) {
            return '#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
        }

        return $value;
    }
}
