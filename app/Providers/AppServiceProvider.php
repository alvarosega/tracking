<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Forzar timezone a nivel de PHP y Carbon
        date_default_timezone_set('America/La_Paz');
        Carbon::setLocale('es');

        // Forzar timezone a nivel de la sesión de MySQL
        DB::statement("SET time_zone = '-04:00'");
    }
}