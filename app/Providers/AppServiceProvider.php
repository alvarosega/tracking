<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

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

        // Forzar timezone a nivel de sesión MySQL de forma segura
        try {
            DB::statement("SET time_zone = '-04:00'");
        } catch (\Throwable) {
            // Previene caídas en entornos de consola o deployments sin red activa
        }
    }
}
