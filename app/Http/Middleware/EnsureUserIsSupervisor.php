<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsSupervisor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active || $user->role?->name !== 'supervisor') {
            abort(403, 'Acceso denegado. Se requieren permisos de supervisor.');
        }

        return $next($request);
    }
}