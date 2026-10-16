<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\ResetPassword;
use App\Actions\Auth\SendPasswordResetLink;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * Mot de passe oublié, en deux temps : on demande un lien, puis on pose un
 * nouveau mot de passe avec le jeton reçu.
 *
 * Les deux routes sont publiques — il n'y a par définition pas de jeton
 * d'API quand on a perdu son mot de passe — et bornées en débit.
 */
class PasswordResetController extends Controller
{
    /**
     * Demander un lien. La réponse ne dit jamais si l'adresse existe.
     */
    public function forgot(ForgotPasswordRequest $request, SendPasswordResetLink $send): JsonResponse
    {
        $send->handle($request->validated('email'));

        return response()->json(['message' => SendPasswordResetLink::GENERIC_MESSAGE]);
    }

    /**
     * Poser le nouveau mot de passe.
     *
     * Aucun jeton d'API n'est rendu : le lien reçu par email ne doit pas
     * suffire à ouvrir une session. L'application repasse par la connexion,
     * ce qui fait aussi retaper le nouveau mot de passe une fois.
     *
     * @throws ValidationException
     */
    public function reset(ResetPasswordRequest $request, ResetPassword $reset): JsonResponse
    {
        $status = $reset->handle($request->validated());

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            // Lien périmé, déjà utilisé, falsifié, ou adresse qui ne
            // correspond pas : un seul message pour tous ces cas.
            throw ValidationException::withMessages([
                'token' => __(PasswordBroker::INVALID_TOKEN),
            ]);
        }

        return response()->json(['message' => __(PasswordBroker::PASSWORD_RESET)]);
    }
}
