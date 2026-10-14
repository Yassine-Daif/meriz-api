<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * L'email de confirmation d'adresse, en français.
 *
 * On n'hérite que du texte : l'URL signée, son expiration et le hash de
 * l'adresse viennent de VerifyEmail, l'implémentation de Laravel.
 */
class VerifyEmailAddress extends VerifyEmail
{
    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject('Confirme ton adresse email')
            ->line('Clique sur le bouton ci-dessous pour confirmer ton adresse email.')
            ->action('Confirmer mon adresse', $url)
            ->line('Ce lien expire dans '.$minutes.' minutes.')
            ->line("Si tu n'as pas créé de compte, ignore ce message.");
    }
}
