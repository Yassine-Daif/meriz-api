<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Entêtes de sécurité sur toutes les réponses, erreurs comprises.
 *
 * L'API ne rend que du JSON, mais une réponse peut finir ouverte dans un
 * navigateur : ces entêtes empêchent qu'elle y soit interprétée, affichée
 * dans un cadre, ou qu'une adresse d'API parte en référent.
 *
 * Rien n'est écrasé : les routes de fichiers posent déjà leur propre CSP et
 * leur propre Cache-Control, et gardent les leurs.
 */
class SecurityHeaders
{
    /**
     * @var array<string, string>
     */
    private const HEADERS = [
        // Le navigateur s'en tient au type annoncé, il ne le devine pas.
        'X-Content-Type-Options' => 'nosniff',
        // Aucune adresse d'API ne part vers un site tiers.
        'Referrer-Policy' => 'no-referrer',
        // Une réponse d'API n'a aucune raison d'être encadrée.
        'X-Frame-Options' => 'DENY',
        // Du JSON ne charge rien : ni script, ni image, ni style.
        'Content-Security-Policy' => "default-src 'none'; frame-ancestors 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $header => $value) {
            if (! $response->headers->has($header)) {
                $response->headers->set($header, $value);
            }
        }

        return $response;
    }
}
