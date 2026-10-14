<?php

namespace App\Enums;

/**
 * Issue d'un lien de vérification d'email.
 *
 * Cette valeur part telle quelle vers le front, en paramètre de la page de
 * retour, pour qu'il affiche le bon message.
 *
 * Invalid couvre aussi bien une signature falsifiée qu'un compte inconnu :
 * on ne révèle pas si une adresse existe.
 */
enum EmailVerificationResult: string
{
    case Success = 'success';
    case Already = 'already';
    case Expired = 'expired';
    case Invalid = 'invalid';
}
