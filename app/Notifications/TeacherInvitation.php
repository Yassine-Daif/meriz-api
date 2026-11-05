<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeacherInvitation extends Notification
{
    public function __construct(private readonly string $token, private readonly string $institution) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Le code reste hors des URL et de leurs journaux d'accès.
        return (new MailMessage)->subject(__('Meriz : invitation professeur'))
            ->greeting(__('Bonjour,'))->line(__('Le référent de :institution vous invite à activer votre espace professeur sur Meriz.', ['institution' => $this->institution]))
            ->line(__('Connectez-vous à Meriz avec cette adresse email, puis saisissez ce code dans votre profil :'))
            ->line($this->token)->line(__('Ce code nominatif est valable 48 heures et ne peut être utilisé qu’une fois.'))
            ->line(__('Si vous n’attendiez pas cette invitation, ignorez ce message.'));
    }
}
