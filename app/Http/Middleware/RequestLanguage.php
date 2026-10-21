<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** La langue ne change que les messages, jamais les contenus des utilisateurs. */
class RequestLanguage
{
    public function handle(Request $request, Closure $next): Response
    {
        // Le choix du client public ne change pas le panneau technique privé.
        if (! $request->is('api', 'api/*', 'broadcasting/auth')) {
            return $next($request);
        }

        $locale = $request->getPreferredLanguage(['fr', 'en']) ?? 'fr';
        app()->setLocale($locale);

        $response = $next($request);

        if ($response instanceof JsonResponse) {
            $payload = $response->getData();
            if (is_object($payload)) {
                if (isset($payload->message) && is_string($payload->message)) {
                    $payload->message = __($payload->message);
                }
                if (isset($payload->errors) && is_object($payload->errors)) {
                    foreach ($payload->errors as $field => $messages) {
                        if (is_array($messages)) {
                            $payload->errors->{$field} = array_map(
                                fn ($message) => is_string($message) ? __($message) : $message,
                                $messages,
                            );
                        }
                    }
                }
                $response->setData($payload);
            }
        }

        $response->headers->set('Content-Language', $locale);
        $response->setVary('Accept-Language', false);

        return $response;
    }
}
