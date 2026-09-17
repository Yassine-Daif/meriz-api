<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Pas de page de connexion : un invité reçoit un 401 JSON, jamais une redirection.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API seule : toute erreur est rendue en JSON, jamais en page HTML.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Un seul 404, sans nom de modèle ni identifiant, même en debug :
        // un document d'autrui est indiscernable d'un document inexistant.
        $exceptions->render(function (HttpExceptionInterface $e) {
            if ($e->getStatusCode() === 404) {
                return response()->json(['message' => 'Ressource introuvable.'], 404);
            }
        });
    })->create();
