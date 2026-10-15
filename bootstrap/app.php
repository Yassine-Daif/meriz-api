<?php

use App\Exceptions\ApiErrorMessages;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    // L'API est par jeton : la route d'autorisation des canaux s'authentifie
    // avec le Bearer token, jamais par cookie de session.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum', 'throttle:broadcasting']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Pas de page de connexion : un invité reçoit un 401 JSON, jamais une redirection.
        $middleware->redirectGuestsTo(fn () => null);

        // Entêtes de sécurité sur toute réponse, erreurs comprises.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // API seule : toute erreur est rendue en JSON, jamais en page HTML.
        $exceptions->shouldRenderJsonWhen(fn () => true);

        // Les trois messages suivants sont écrits en dur dans le framework :
        // aucun fichier de langue ne les atteint, on les rend donc ici.
        $exceptions->render(fn (AuthenticationException $e) => response()->json(
            ['message' => ApiErrorMessages::UNAUTHENTICATED],
            401,
        ));

        // Les entêtes sont conservés : Retry-After dit quand réessayer.
        $exceptions->render(fn (ThrottleRequestsException $e) => response()->json(
            ['message' => ApiErrorMessages::TOO_MANY_REQUESTS],
            429,
            $e->getHeaders(),
        ));

        // 404, 405, 413, 503, et le 403 sans message : voir ApiErrorMessages.
        // Un refus de Policy, déjà en français, sort intact.
        $exceptions->render(function (HttpExceptionInterface $e) {
            $message = ApiErrorMessages::forHttpException($e);

            return $message === null
                ? null
                : response()->json(['message' => $message], $e->getStatusCode());
        });

        // Dernier filet : en production, une erreur inattendue répond en
        // français. En debug, on laisse Laravel afficher la trace.
        $exceptions->render(function (Throwable $e) {
            if (config('app.debug')
                || $e instanceof HttpExceptionInterface
                || $e instanceof HttpResponseException
                || $e instanceof ValidationException) {
                return null;
            }

            return response()->json(['message' => ApiErrorMessages::SERVER_ERROR], 500);
        });
    })->create();
