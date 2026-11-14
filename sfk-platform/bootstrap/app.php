<?php

use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Modules\Core\Http\Middleware\EnsureAccountIsActive;
use Modules\Core\Http\Middleware\SecurityHeaders;
use Modules\Core\Http\Middleware\StripHtmlIndentation;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // browser protections on every response (CSP, framing, HSTS over HTTPS …)
        $middleware->append(SecurityHeaders::class);
        // pages without the indentation of their HTML: about two thirds smaller (Modules\Core\Http\HtmlIndentation)
        $middleware->append(StripHtmlIndentation::class);
        // a user who may no longer sign in (在籍状況) is signed out — also an open session or a remember-me cookie
        $middleware->appendToGroup('web', EnsureAccountIsActive::class);
        // …before the dashboards' authentication, which would only answer 403 and leave the session open
        $middleware->prependToPriorityList(before: AuthenticatesRequests::class, prepend: EnsureAccountIsActive::class);

        // behind a load balancer / TLS proxy: its addresses (comma separated, or *), so HTTPS and client IPs are seen
        if (filled($proxies = env('TRUSTED_PROXIES'))) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : array_map('trim', explode(',', (string) $proxies)));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
