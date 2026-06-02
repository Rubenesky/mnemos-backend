<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        // Si no está autenticado, redirige al login
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // Si su rol no está entre los permitidos, devuelve 403
        if (!in_array(auth()->user()->role, $roles)) {
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}