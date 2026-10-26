<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Browser protections on every response (global middleware, so they hold wherever the system is hosted):
 * - pages may only load scripts, styles, images, fonts and connections from this system (Content-Security-Policy;
 *   inline scripts and eval stay allowed: Livewire, Alpine and Filament need them), may not be framed by other sites
 *   and may only post forms here;
 * - no MIME sniffing, no referrer to other sites, no camera / microphone / location / payment APIs, a window of its own
 *   (no access from pages that opened it);
 * - over HTTPS, browsers keep using HTTPS for a year (HSTS).
 * A response that brings its own Content-Security-Policy keeps it (e.g. private files: `sandbox`); non-HTML responses
 * (PDF, ZIP, JSON) get no CSP, so the browser's PDF viewer works.
 */
final class SecurityHeaders
{
    private const CONTENT_SECURITY_POLICY = "default-src 'self'; "
        ."script-src 'self' 'unsafe-inline' 'unsafe-eval'; "
        ."style-src 'self' 'unsafe-inline'; "
        ."img-src 'self' data: blob:; "
        ."font-src 'self' data: blob:; "
        ."connect-src 'self'; "
        ."worker-src 'self' blob:; "
        ."frame-src 'self'; "
        ."frame-ancestors 'self'; "
        ."form-action 'self'; "
        ."base-uri 'self'; "
        ."object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $headers->remove('X-Powered-By');

        if (! $headers->has('Content-Security-Policy') && str_starts_with((string) $headers->get('Content-Type', 'text/html'), 'text/html')) {
            $headers->set('Content-Security-Policy', self::CONTENT_SECURITY_POLICY);
        }

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
