<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sets the application locale based on the Accept-Language header or a query parameter.
 *
 * @package App\Http\Middleware
 */
class SetLocale
{
    /**
     * Handle an incoming request.
     * Checks: 1) ?lang=es query param, 2) Accept-Language header, 3) fallback to 'en'
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = 'en';

        if ($request->has('lang') && in_array($request->query('lang'), ['en', 'es'])) {
            $locale = $request->query('lang');
        } elseif ($request->hasHeader('Accept-Language')) {
            $acceptLang = substr($request->header('Accept-Language'), 0, 2);
            if (in_array($acceptLang, ['en', 'es'])) {
                $locale = $acceptLang;
            }
        }

        app()->setLocale($locale);
        return $next($request);
    }
}
