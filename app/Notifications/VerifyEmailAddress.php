<?php

namespace App\Notifications;

use App\Enums\UsageCategory;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * L'email de confirmation, dans la langue de la demande.
 *
 * On n'hérite que du texte : l'URL signée, son expiration et le hash de
 * l'adresse viennent de VerifyEmail, l'implémentation de Laravel.
 */
class VerifyEmailAddress extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        if ($notifiable instanceof User && $notifiable->usage_category === UsageCategory::Teacher && $notifiable->is_academic) {
            $minutes = (int) config('auth.verification.expire', 60);

            return (new MailMessage)
                ->subject(__('Activez votre espace professeur Meriz'))
                ->greeting(__('Bonjour !'))
                ->line(__('Confirmez votre adresse d’établissement pour activer automatiquement votre espace professeur.'))
                ->action(__('Activer mon espace professeur'), $this->verificationUrl($notifiable))
                ->line(__('Ce lien expire dans :minutes minutes.', ['minutes' => $minutes]))
                ->line(__("Si tu n'as pas créé de compte, ignore ce message."))
                ->salutation(__('L’équipe Meriz'));
        }

        return parent::toMail($notifiable);
    }

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = (int) config('auth.verification.expire', 60);

        return (new MailMessage)
            ->subject(__('Confirme ton adresse email'))
            ->greeting(__('Bonjour !'))
            ->line(__('Clique sur le bouton ci-dessous pour confirmer ton adresse email.'))
            ->action(__('Confirmer mon adresse'), $url)
            ->line(__('Ce lien expire dans :minutes minutes.', ['minutes' => $minutes]))
            ->line(__("Si tu n'as pas créé de compte, ignore ce message."))
            ->salutation(__('L’équipe Meriz'));
    }
}
