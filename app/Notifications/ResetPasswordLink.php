<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * L'email de réinitialisation, dans la langue de la demande.
 *
 * Le lien mène droit à l'application, pas à l'API : c'est elle qui demande le
 * nouveau mot de passe, puis le renvoie au serveur avec le jeton. Son adresse
 * vient uniquement de la configuration, jamais de la requête — sans quoi un
 * en-tête bien choisi suffirait à détourner le lien vers un autre site.
 *
 * Le jeton, lui, est fabriqué et haché en base par Laravel.
 */
class ResetPasswordLink extends ResetPassword
{
    /**
     * @param  mixed  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        $base = rtrim((string) config('frontend.url'), '/');
        $path = '/'.ltrim((string) config('frontend.password_reset_path'), '/');

        return $base.$path.'?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
            'lang' => app()->getLocale() === 'en' ? 'en' : null,
        ]);
    }

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60);

        return (new MailMessage)
            ->subject(__('Réinitialise ton mot de passe'))
            ->greeting(__('Bonjour !'))
            ->line(__('Tu reçois cet email parce que quelqu\'un a demandé un nouveau mot de passe pour ton compte Meriz.'))
            ->action(__('Choisir un nouveau mot de passe'), $url)
            ->line(__('Ce lien expire dans :minutes minutes, et ne sert qu’une fois.', ['minutes' => $minutes]))
            ->line(__('Si tu n\'as rien demandé, ignore ce message : ton mot de passe reste inchangé.'))
            ->salutation(__('L’équipe Meriz'));
    }
}
