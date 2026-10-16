<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Pose un nouveau mot de passe à partir d'un jeton reçu par email.
 *
 * Le jeton, son expiration et son usage unique sont gérés par le courtier de
 * Laravel ; il est haché en base, donc une fuite de la table ne donne aucun
 * lien utilisable.
 *
 * Trois choses se passent au moment du changement, et les deux dernières
 * comptent autant que la première :
 *
 * 1. le mot de passe change ;
 * 2. l'adresse devient vérifiée — suivre ce lien prouve exactement ce que
 *    prouve le lien de vérification : on lit cette boîte ;
 * 3. **tous les jetons tombent.** C'est ici qu'un compte volé se reprend :
 *    changer le mot de passe ne sert à rien si la session du voleur survit.
 */
class ResetPassword
{
    /**
     * @param  array{token: string, email: string, password: string}  $data
     * @return string Un des codes de PasswordBroker
     */
    public function handle(array $data): string
    {
        $data['email'] = Str::lower(trim($data['email']));

        // Un compte supprimé par son titulaire ne se reprend pas, et on ne le
        // distingue pas d'un lien périmé.
        if (User::where('email', $data['email'])->value('anonymized_at') !== null) {
            return PasswordBroker::INVALID_TOKEN;
        }

        return Password::reset($data, function (User $user, string $password) {
            $user->forceFill([
                'password' => $password,
                'email_verified_at' => $user->email_verified_at ?? now(),
                'remember_token' => null,
            ])->save();

            // Toutes les sessions en cours tombent, sur tous les appareils.
            $user->tokens()->delete();

            event(new PasswordReset($user));
        });
    }
}
