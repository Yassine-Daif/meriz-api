<?php

namespace App\Services;

/**
 * Dit si un email appartient à un domaine scolaire ou universitaire.
 *
 * La liste des domaines vit dans config/academic.php. Ici, seule la
 * règle de correspondance : un motif couvre le domaine et ses
 * sous-domaines, et * vaut un seul morceau de domaine, sans point.
 */
class AcademicEmailChecker
{
    /**
     * @param  list<string>  $patterns
     */
    public function __construct(private readonly array $patterns) {}

    public function isAcademic(string $email): bool
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $domain = strtolower(trim(substr($email, $at + 1)));

        if ($domain === '') {
            return false;
        }

        foreach ($this->patterns as $pattern) {
            if (preg_match($this->toRegex($pattern), $domain) === 1) {
                return true;
            }
        }

        return false;
    }

    private function toRegex(string $pattern): string
    {
        $quoted = preg_quote(strtolower(trim($pattern)), '/');
        $body = str_replace('\*', '[a-z0-9-]+', $quoted);

        return '/^(?:[a-z0-9-]+\.)*'.$body.'$/D';
    }
}
