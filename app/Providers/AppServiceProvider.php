<?php

namespace App\Providers;

use App\Models\Assignment;
use App\Models\Classroom;
use App\Models\Document;
use App\Models\Submission;
use App\Models\User;
use App\Services\AcademicEmailChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
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

        $this->configureRateLimiting();
        $this->configureRouteBindings();
    }

    /**
     * {document} n'est cherché que parmi les documents de l'utilisateur
     * connecté. Le document d'un autre lève la même exception qu'un
     * document inexistant, donc le même 404.
     */
    private function configureRouteBindings(): void
    {
        Route::bind('document', function (string $value) {
            $user = request()->user();

            if (! $user || ! Str::isUlid($value)) {
                throw (new ModelNotFoundException)->setModel(Document::class);
            }

            return $user->documents()->whereKey($value)->firstOrFail();
        });

        // {classroom} n'est cherchée que parmi les classes que l'utilisateur
        // enseigne ou dont il est membre. Sinon, même 404 qu'inexistante.
        Route::bind('classroom', function (string $value) {
            $user = request()->user();

            if (! $user || ! Str::isUlid($value)) {
                throw (new ModelNotFoundException)->setModel(Classroom::class);
            }

            return Classroom::visibleTo($user)->whereKey($value)->firstOrFail();
        });

        // {assignment} n'est cherché que parmi les devoirs visibles : tous ceux
        // des classes que l'utilisateur enseigne, les publiés de ses classes.
        // Un brouillon, pour un élève, donne le même 404 qu'un devoir inexistant.
        Route::bind('assignment', function (string $value) {
            $user = request()->user();

            if (! $user || ! Str::isUlid($value)) {
                throw (new ModelNotFoundException)->setModel(Assignment::class);
            }

            return Assignment::visibleTo($user)->whereKey($value)->firstOrFail();
        });

        // {submission} n'est cherché que parmi les rendus visibles : les siens,
        // et ceux des devoirs des classes que l'utilisateur enseigne. Le rendu
        // d'un autre élève donne le même 404 qu'un rendu inexistant.
        Route::bind('submission', function (string $value) {
            $user = request()->user();

            if (! $user || ! Str::isUlid($value)) {
                throw (new ModelNotFoundException)->setModel(Submission::class);
            }

            return Submission::visibleTo($user)->whereKey($value)->firstOrFail();
        });

        // {member} n'est cherché que parmi les membres de la classe déjà résolue.
        Route::bind('member', function (string $value, RoutingRoute $route) {
            $classroom = $route->parameter('classroom');

            if (! $classroom instanceof Classroom || ! ctype_digit($value)) {
                throw (new ModelNotFoundException)->setModel(User::class);
            }

            return $classroom->members()->whereKey((int) $value)->firstOrFail();
        });
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            ];
        });

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
    }
}
