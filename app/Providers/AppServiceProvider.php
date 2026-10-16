<?php

namespace App\Providers;

use App\Routing\ScopedRouteBindings;
use App\Services\AcademicEmailChecker;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            AcademicEmailChecker::class,
            fn ($app) => new AcademicEmailChecker($app['config']->get('academic.domains', [])),
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(8));

        // bootstrap/app.php n'appelle pas withEvents() : le câblage
        // automatique du framework ne s'applique pas, on déclare donc
        // nous-mêmes l'envoi du lien de vérification à l'inscription.
        Event::listen(Registered::class, SendEmailVerificationNotification::class);

        $this->configureRateLimiting();

        // La première barrière du cloisonnement : voir ScopedRouteBindings.
        (new ScopedRouteBindings)->register();
    }

    private function configureRateLimiting(): void
    {
        // Plafond général de l'API : il assure la couverture, pas la finesse.
        // Les routes sensibles gardent en plus leur limite propre, bien plus
        // basse ; les deux s'appliquent, la plus basse gagne. Sans lui, un
        // jeton volé aspirerait toutes les lectures à pleine vitesse.
        RateLimiter::for('api', fn (Request $request) => [
            Limit::perMinute(300)->by('api:'.($request->user()?->id ?: $request->ip())),
        ]);

        // Autorisation d'un canal websocket : un abonnement légitime est rare,
        // même en co-édition, car le canal reste ensuite ouvert.
        RateLimiter::for('broadcasting', fn (Request $request) => [
            Limit::perMinute(120)->by('broadcast:'.($request->user()?->id ?: $request->ip())),
        ]);

        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

        // Contre la devinette de codes de groupe.
        RateLimiter::for('join-group', fn (Request $request) => [
            Limit::perMinute(10)->by('join-group:'.$request->user()?->id),
            Limit::perHour(50)->by('join-group-hour:'.$request->user()?->id),
            Limit::perMinute(30)->by('join-group-ip:'.$request->ip()),
        ]);

        // Contre la devinette de codes de classe.
        RateLimiter::for('join-classroom', fn (Request $request) => [
            Limit::perMinute(10)->by('join:'.$request->user()?->id),
            Limit::perHour(50)->by('join-hour:'.$request->user()?->id),
            Limit::perMinute(30)->by('join-ip:'.$request->ip()),
        ]);

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(5)->by('register:'.$request->ip()),
            Limit::perHour(20)->by('register-hour:'.$request->ip()),
        ]);

        // Mot de passe oublié. Deux bornes, pour deux abus différents : par
        // adresse demandée, pour qu'on n'inonde pas une boîte précise ; par
        // IP, pour qu'on ne balaye pas une liste d'adresses.
        RateLimiter::for('password-forgot', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(3)->by('forgot:'.$email),
                Limit::perHour(10)->by('forgot-hour:'.$email),
                Limit::perMinute(10)->by('forgot-ip:'.$request->ip()),
            ];
        });

        // Le jeton n'est pas devinable, mais une limite coûte peu.
        RateLimiter::for('password-reset', fn (Request $request) => [
            Limit::perMinute(6)->by('reset-ip:'.$request->ip()),
        ]);

        // Export de ses données : une requête lourde, qu'on ne répète pas
        // en boucle.
        RateLimiter::for('export-data', fn (Request $request) => [
            Limit::perHour(3)->by('export:'.$request->user()?->id),
            Limit::perDay(10)->by('export-day:'.$request->user()?->id),
        ]);

        // Renvoi du lien de vérification : un email coûte cher, et sert à
        // inonder une boîte si on ne le borne pas.
        RateLimiter::for('verify-email', fn (Request $request) => [
            Limit::perMinute(2)->by('verify-email:'.$request->user()?->id),
            Limit::perHour(10)->by('verify-email-hour:'.$request->user()?->id),
            Limit::perMinute(10)->by('verify-email-ip:'.$request->ip()),
        ]);
    }
}
