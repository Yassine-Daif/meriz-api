<?php

namespace App\Enums;

/**
 * Issue d'une demande de passage en mode prof.
 *
 * Aujourd'hui, toute demande éligible est approuvée tout de suite.
 * Quand la validation par un administrateur arrivera, on ajoutera
 * un cas Pending, renvoyé tant que la demande attend.
 */
enum TeacherRequestStatus: string
{
    case Approved = 'approved';
}
