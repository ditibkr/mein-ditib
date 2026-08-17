<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // HTTPS erzwingen in Production
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }

        // Starke Passwort-Standardregeln
        Password::defaults(function () {
            return app()->isProduction()
                ? Password::min(8)->letters()->mixedCase()->numbers()->symbols()->uncompromised()
                : Password::min(8);
        });
    }
}
