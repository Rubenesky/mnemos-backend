<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Embed routes must be embeddable in external websites — override the default DENY
        if ($request->is('api/public/embed/*')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors *; default-src 'self' 'unsafe-inline' https: data:;");
            $response->headers->remove('X-Frame-Options');
        } else {
            $response->headers->set('X-Frame-Options', 'DENY');
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
