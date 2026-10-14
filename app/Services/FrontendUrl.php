<?php

namespace App\Services;

use App\Enums\EmailVerificationResult;

/**
 * Fabrique les adresses de retour vers l'application.
 *
 * L'adresse vient uniquement de la configuration : aucun paramètre de
 * requête n'entre ici, sinon le serveur deviendrait une redirection
 * ouverte vers n'importe quel site.
 */
class FrontendUrl
{
    public function verificationLanding(EmailVerificationResult $result): string
    {
        $base = rtrim((string) config('frontend.url'), '/');
        $path = '/'.ltrim((string) config('frontend.email_verified_path'), '/');

        return $base.$path.'?'.http_build_query(['verification' => $result->value]);
    }
}
