<?php

namespace App\Providers;

use App\Models\Document;
use App\Services\AcademicEmailChecker;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
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

        RateLimiter::for('register', fn (Request $request) => [
            Limit::perMinute(5)->by('register:'.$request->ip()),
            Limit::perHour(20)->by('register-hour:'.$request->ip()),
        ]);
    }
}
