<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasModuleAccess
{
    public function handle(Request $request, Closure $next, string $moduleKey, ?string $submoduleKey = null, ?string $mode = null): Response
    {
        $user = $request->user();

        if (!$user || !$user->is_active) {
            abort(403, 'Usuario inactivo o sesión no válida.');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $requireWrite = ($mode === 'write');

        if (!$user->hasModuleAccess($moduleKey, $submoduleKey, $requireWrite)) {
            $label = $submoduleKey ? "{$moduleKey} ({$submoduleKey})" : $moduleKey;
            $actionText = $requireWrite ? 'modificar datos en' : 'acceder a';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => "No tienes permisos asignados para {$actionText} '{$label}'.",
                ], 403);
            }

            return redirect()->route('supervisor.index')->with('warning', "No tienes permisos asignados para {$actionText} '{$label}'.");
        }

        return $next($request);
    }
}

