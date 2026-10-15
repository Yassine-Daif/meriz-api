<?php

use App\Exceptions\ApiErrorMessages;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * La zone d'administration est la seule surface qui rend des pages. Tout le
 * reste du serveur ne parle que JSON, donc chaque réglage de rendu d'erreur
 * se retire sur ces chemins, sinon le panneau afficherait du JSON au lieu de
 * ses pages.
 */
$isAdminPanel = fn (Request $request) => $request->is('admin', 'admin/*');

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
        // Pas de page de connexion dans l'API : un invité reçoit un 401 JSON,
        // jamais une redirection. Le panneau admin a son propre garde, qui
        // renvoie vers sa page de connexion.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*') ? '/admin/login' : null,
        );

        // Entêtes de sécurité sur toute réponse, erreurs comprises.
        $middleware->append(SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions) use ($isAdminPanel): void {
        // API seule : toute erreur est rendue en JSON, jamais en page HTML.
        // Sauf dans le panneau admin, qui doit afficher ses pages.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ! $isAdminPanel($request),
        );

        // Les trois messages suivants sont écrits en dur dans le framework :
        // aucun fichier de langue ne les atteint, on les rend donc ici.
        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isAdminPanel) {
            return $isAdminPanel($request)
                ? null
                : response()->json(['message' => ApiErrorMessages::UNAUTHENTICATED], 401);
        });

        // Les entêtes sont conservés : Retry-After dit quand réessayer.
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) use ($isAdminPanel) {
            return $isAdminPanel($request)
                ? null
                : response()->json(
                    ['message' => ApiErrorMessages::TOO_MANY_REQUESTS],
                    429,
                    $e->getHeaders(),
                );
        });

        // 404, 405, 413, 503, et le 403 sans message : voir ApiErrorMessages.
        // Un refus de Policy, déjà en français, sort intact.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) use ($isAdminPanel) {
            $message = $isAdminPanel($request) ? null : ApiErrorMessages::forHttpException($e);

            return $message === null
                ? null
                : response()->json(['message' => $message], $e->getStatusCode());
        });

        // Dernier filet : en production, une erreur inattendue répond en
        // français. En debug, on laisse Laravel afficher la trace.
        $exceptions->render(function (Throwable $e, Request $request) use ($isAdminPanel) {
            if (config('app.debug')
                || $isAdminPanel($request)
                || $e instanceof HttpExceptionInterface
                || $e instanceof HttpResponseException
                || $e instanceof ValidationException) {
                return null;
            }

            return response()->json(['message' => ApiErrorMessages::SERVER_ERROR], 500);
        });
    })->create();
