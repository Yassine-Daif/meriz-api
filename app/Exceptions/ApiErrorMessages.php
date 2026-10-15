<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Les messages d'erreur que Laravel écrit en dur, en français.
 *
 * Un fichier de langue ne suffit pas pour ceux-là : « Unauthenticated. »,
 * « Too Many Attempts. » et « This action is unauthorized. » sont des
 * chaînes figées dans le framework, jamais passées par le traducteur. On les
 * remplace donc au rendu de l'exception.
 *
 * Règle du 403 : on ne remplace que le défaut anglais. Les refus écrits par
 * les Policies sont déjà en français et doivent sortir intacts.
 */
class ApiErrorMessages
{
    public const UNAUTHENTICATED = 'Non authentifié.';

    public const TOO_MANY_REQUESTS = 'Trop de requêtes, réessaie dans un instant.';

    public const FORBIDDEN = 'Action non autorisée.';

    public const NOT_FOUND = 'Ressource introuvable.';

    public const METHOD_NOT_ALLOWED = 'Méthode non autorisée.';

    public const PAYLOAD_TOO_LARGE = 'Requête trop volumineuse.';

    public const SERVICE_UNAVAILABLE = 'Service momentanément indisponible.';

    public const SERVER_ERROR = 'Erreur du serveur.';

    /**
     * Le défaut anglais d'une autorisation refusée sans message.
     */
    private const ENGLISH_FORBIDDEN = 'This action is unauthorized.';

    /**
     * Le message français d'un statut, ou null si on laisse passer la
     * réponse telle quelle.
     */
    public static function forHttpException(HttpExceptionInterface $e): ?string
    {
        return match ($e->getStatusCode()) {
            // Un seul 404, sans nom de modèle ni identifiant, même en debug :
            // le document d'autrui est indiscernable d'un document inexistant.
            404 => self::NOT_FOUND,
            // Le message de Symfony décrit la table de routage, en anglais.
            405 => self::METHOD_NOT_ALLOWED,
            413 => self::PAYLOAD_TOO_LARGE,
            503 => self::SERVICE_UNAVAILABLE,
            403 => self::forbiddenOrNull($e->getMessage()),
            default => null,
        };
    }

    private static function forbiddenOrNull(string $message): ?string
    {
        return $message === '' || $message === self::ENGLISH_FORBIDDEN
            ? self::FORBIDDEN
            : null;
    }
}
