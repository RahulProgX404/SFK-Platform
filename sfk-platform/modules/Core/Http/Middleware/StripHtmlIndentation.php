<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Modules\Core\Http\HtmlIndentation;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pages without their indentation (HtmlIndentation): smaller to send, quicker for the browser to read. Only whole HTML
 * pages — files, streams, JSON (the Livewire updates, stripped by Livewire\StripsIndentation) and PDFs pass as they are.
 */
final class StripHtmlIndentation
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse
            || ! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $content = $response->getContent();
        if (is_string($content) && $content !== '') {
            // a Laravel response keeps the view it came from (setContent() would replace it with the string)
            $original = $response instanceof IlluminateResponse ? $response->original : null;
            $response->setContent(HtmlIndentation::strip($content));
            if ($response instanceof IlluminateResponse) {
                $response->original = $original;
            }
        }

        return $response;
    }
}
