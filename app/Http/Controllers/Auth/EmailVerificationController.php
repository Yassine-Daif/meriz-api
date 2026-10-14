<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\VerifyEmailLink;
use App\Http\Controllers\Controller;
use App\Services\FrontendUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Confirmation d'adresse email.
 *
 * La route de vérification est publique : le lien est ouvert depuis une
 * boîte mail, sans jeton. C'est sa signature qui l'authentifie. Elle ne
 * répond pas en JSON, elle renvoie la personne vers l'application.
 */
class EmailVerificationController extends Controller
{
    public const SENT_MESSAGE = 'Lien de vérification envoyé.';

    public const ALREADY_MESSAGE = 'Cet email est déjà vérifié.';

    public function verify(
        Request $request,
        int|string $id,
        string $hash,
        VerifyEmailLink $verify,
        FrontendUrl $frontend,
    ): RedirectResponse {
        $result = $verify->handle($request, $id, $hash);

        return redirect()->away($frontend->verificationLanding($result));
    }

    /**
     * Renvoyer le lien, pour son propre compte seulement.
     */
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => self::ALREADY_MESSAGE]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => self::SENT_MESSAGE], 202);
    }
}
