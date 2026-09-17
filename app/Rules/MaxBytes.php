<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Limite une chaîne en octets. La règle max de Laravel compte des
 * caractères, ce qui laisse passer bien plus d'octets en UTF-8.
 */
class MaxBytes implements ValidationRule
{
    public function __construct(private readonly int $bytes) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > $this->bytes) {
            $fail('Le contenu dépasse la taille maximale autorisée ('.$this->humanSize().').');
        }
    }

    private function humanSize(): string
    {
        return $this->bytes >= 1024 * 1024
            ? round($this->bytes / (1024 * 1024), 1).' Mo'
            : round($this->bytes / 1024, 1).' Ko';
    }
}
