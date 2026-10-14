<?php

namespace App\Actions\Auth;

use App\Enums\EmailVerificationResult;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Valide un lien de vérification d'email, et vérifie le compte.
 *
 * Toute la décision tient ici. Le lien est authentifié par sa signature,
 * pas par un jeton : il est ouvert depuis une boîte mail. Une fois le
 * compte vérifié, le lien ne revérifie plus rien.
 */
class VerifyEmailLink
{
    public function handle(Request $request, int|string $id, string $hash): EmailVerificationResult
    {
        // 1. Le lien a-t-il bien été fabriqué par ce serveur, et est-il
        //    encore dans sa fenêtre de validité ?
        if (! URL::hasCorrectSignature($request)) {
            return EmailVerificationResult::Invalid;
        }

        if (! URL::signatureHasNotExpired($request)) {
            return EmailVerificationResult::Expired;
        }

        $user = ctype_digit((string) $id) ? User::find((int) $id) : null;

        // 2. Compte inconnu, ou adresse changée depuis l'envoi : même
        //    réponse qu'un lien falsifié, on ne confirme pas qu'un compte
        //    existe.
        if ($user === null || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            return EmailVerificationResult::Invalid;
        }

        // 3. Usage unique : un lien déjà suivi ne réécrit rien et ne
        //    réémet pas l'événement.
        if ($user->hasVerifiedEmail()) {
            return EmailVerificationResult::Already;
        }

        $user->markEmailAsVerified();

        event(new Verified($user));

        return EmailVerificationResult::Success;
    }
}
