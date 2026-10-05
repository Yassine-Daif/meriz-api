<?php

namespace App\Services;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Génère et normalise les codes de classe.
 */
class JoinCodeGenerator
{
    private const MAX_ATTEMPTS = 5;

    /**
     * Code unique pour le modèle visé : les classes par défaut, les groupes
     * en passant leur classe.
     *
     * @param  class-string<Model>  $model
     */
    public function generate(string $model = Classroom::class): string
    {
        for ($i = 0; $i < self::MAX_ATTEMPTS; $i++) {
            $code = $this->randomCode();

            if (! $model::where('join_code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('Impossible de générer un code unique.');
    }

    /**
     * Majuscules, sans espaces ni tirets : "abcd-efgh " devient "ABCDEFGH".
     */
    public function normalize(string $code): string
    {
        return strtoupper(preg_replace('/[\s\-]+/u', '', $code) ?? '');
    }

    private function randomCode(): string
    {
        $alphabet = config('classrooms.code_alphabet');
        $max = strlen($alphabet) - 1;
        $code = '';

        for ($i = 0; $i < config('classrooms.code_length'); $i++) {
            $code .= $alphabet[random_int(0, $max)];
        }

        return $code;
    }
}
