<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Throwable;

/**
 * Envoie un lien de réinitialisation, sans jamais dire si l'adresse existe.
 *
 * C'est tout l'enjeu de cette action : la réponse est **la même** dans tous
 * les cas — adresse connue, adresse inconnue, délai d'attente non écoulé,
 * compte supprimé. Laravel aide déjà sur le temps de réponse, puisque
 * sendResetLink est enveloppé dans un timebox ; il ne reste qu'à ne pas
 * trahir l'information par le message.
 */
class SendPasswordResetLink
{
    public const GENERIC_MESSAGE = 'Si un compte existe pour cette adresse, un lien de réinitialisation vient de partir.';

    public function handle(string $email): void
    {
        $email = Str::lower(trim($email));

        // Un compte supprimé par son titulaire ne se reprend pas. On sort
        // sans rien envoyer, et l'appelant répondra comme d'habitude.
        if (User::where('email', $email)->value('anonymized_at') !== null) {
            return;
        }

        try {
            // Le code de retour est volontairement ignoré. passwords.user et
            // passwords.throttled sont traduits, mais ils ne doivent jamais
            // sortir : le premier dirait qu'une adresse est inconnue, le
            // second qu'elle est connue. Ne pas « corriger » ça.
            Password::sendResetLink(['email' => $email]);
        } catch (Throwable $e) {
            // Service de mail en panne : la requête réussit quand même, sinon
            // l'échec lui-même révélerait que l'adresse existe.
            Log::error("Envoi du lien de réinitialisation impossible pour {$email}.", [
                'exception' => $e,
            ]);
        }
    }
}
