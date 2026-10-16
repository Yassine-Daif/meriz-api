<?php

namespace Tests\Feature\Security;

use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as Router;
use Tests\TestCase;

/**
 * Ces tests lisent la table de routage, pas une route en particulier : ils
 * tombent donc dès qu'une route future oublie le jeton ou sa limite de
 * débit, sans qu'on ait à y penser.
 */
class RouteCoverageTest extends TestCase
{
    /**
     * Les seules routes volontairement ouvertes, chacune pour une raison.
     *
     * @var array<string, string>
     */
    private const PUBLIC_ROUTES = [
        'api/health' => 'Sonde de disponibilité : aucune donnée.',
        'api/auth/register' => "Création de compte : il n'y a pas encore de jeton.",
        'api/auth/login' => 'Obtention du jeton.',
        'api/auth/email/verify/{id}/{hash}' => "Lien ouvert depuis une boîte mail : c'est la signature qui authentifie.",
        'api/auth/password/forgot' => "Mot de passe oublié : par définition, il n'y a pas de jeton.",
        'api/auth/password/reset' => "Le jeton reçu par email tient lieu de preuve, il n'y en a pas d'autre.",
    ];

    /**
     * @return Collection<int, Route>
     */
    private function apiRoutes(): Collection
    {
        return collect(Router::getRoutes()->getRoutes())
            ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/'));
    }

    private function label(Route $route): string
    {
        return implode('|', $route->methods()).' /'.$route->uri();
    }

    public function test_every_api_route_requires_a_token_except_the_listed_public_ones(): void
    {
        $this->assertNotEmpty($this->apiRoutes());

        foreach ($this->apiRoutes() as $route) {
            $middleware = $route->gatherMiddleware();
            $isPublic = array_key_exists($route->uri(), self::PUBLIC_ROUTES);

            if ($isPublic) {
                $this->assertNotContains('auth:sanctum', $middleware, $this->label($route).' : route listée comme publique mais protégée, mets à jour la liste.');

                continue;
            }

            $this->assertContains(
                'auth:sanctum',
                $middleware,
                $this->label($route).' : aucune protection par jeton. Si elle doit être publique, ajoute-la à PUBLIC_ROUTES avec sa raison.',
            );
        }
    }

    public function test_every_api_route_is_rate_limited(): void
    {
        foreach ($this->apiRoutes() as $route) {
            $throttles = array_filter(
                $route->gatherMiddleware(),
                fn ($middleware) => is_string($middleware) && str_starts_with($middleware, 'throttle:'),
            );

            $this->assertNotEmpty(
                $throttles,
                $this->label($route).' : aucune limite de débit.',
            );
        }
    }

    public function test_the_sensitive_routes_keep_their_own_limiter(): void
    {
        $expected = [
            'api/auth/login' => 'throttle:login',
            'api/auth/register' => 'throttle:register',
            'api/auth/email/verification-notification' => 'throttle:verify-email',
            'api/classrooms/join' => 'throttle:join-classroom',
            'api/groups/join' => 'throttle:join-group',
            'api/me/export' => 'throttle:export-data',
        ];

        foreach ($expected as $uri => $limiter) {
            $route = $this->apiRoutes()->first(fn (Route $route) => $route->uri() === $uri);

            $this->assertNotNull($route, "Route disparue : {$uri}");
            $this->assertContains($limiter, $route->gatherMiddleware(), "{$uri} a perdu {$limiter}.");
        }
    }

    public function test_deleting_an_account_is_rate_limited_on_its_own(): void
    {
        $route = $this->apiRoutes()->first(
            fn (Route $route) => $route->uri() === 'api/me' && in_array('DELETE', $route->methods(), true),
        );

        $this->assertNotNull($route);
        $this->assertContains('throttle:5,60', $route->gatherMiddleware());
    }

    public function test_the_channel_authorization_route_is_protected_and_limited(): void
    {
        $route = collect(Router::getRoutes()->getRoutes())
            ->first(fn (Route $route) => $route->uri() === 'broadcasting/auth');

        $this->assertNotNull($route, 'Route broadcasting/auth absente.');

        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth:sanctum', $middleware);
        $this->assertContains('throttle:broadcasting', $middleware);
    }
}
