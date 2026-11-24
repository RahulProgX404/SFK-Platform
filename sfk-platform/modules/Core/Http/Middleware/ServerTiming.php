<?php

declare(strict_types=1);

namespace Modules\Core\Http\Middleware;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpFoundation\Response;

/**
 * Performance measurement (opt-in, SFK_SERVER_TIMING=true — never on in production): every response carries a standard
 * `Server-Timing` header that the browser's DevTools show under Network → Timing — the time PHP spent on the request
 * (`app`, from LARAVEL_START), the time in the database (`db`) and the number of queries (`q`). With
 * SFK_SERVER_TIMING_LOG=true each request also adds one line to storage/logs/server-timing.log (method, path, Livewire
 * calls, status, times, queries, peak memory) for percentiles over many interactions (docs/performance.md), followed by
 * the request's queries — their time and SQL without the bound values — on indented lines.
 *
 * The queries are counted from the moment the Core module boots (listen()), so the service providers' own queries
 * are included.
 */
final class ServerTiming
{
    private static int $queries = 0;

    private static float $dbMs = 0.0;

    /** @var list<string> the queries of the request for the log: time and SQL (no bound values) */
    private static array $sql = [];

    /** the event dispatcher counted on (a new application — tests — gets a new one) */
    private static ?int $listening = null;

    public static function enabled(): bool
    {
        return (bool) config('core.server_timing');
    }

    /** Starts counting the queries (CoreServiceProvider::boot, only when enabled). */
    public static function listen(): void
    {
        $dispatcher = spl_object_id(Event::getFacadeRoot());
        if (self::$listening === $dispatcher) {
            return;
        }
        self::$listening = $dispatcher;
        Event::listen(QueryExecuted::class, function (QueryExecuted $query): void {
            self::$queries++;
            self::$dbMs += $query->time;
            if (config('core.server_timing_log') && count(self::$sql) < 200) {
                self::$sql[] = sprintf('%.1f', $query->time)."\t".preg_replace('/\s+/', ' ', mb_strimwidth($query->sql, 0, 400, '…'));
            }
        });
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! self::enabled()) {
            return $next($request);
        }

        // a long-lived worker (queue, Octane) keeps the statics: count from this request on
        self::$queries = 0;
        self::$dbMs = 0.0;
        self::$sql = [];
        $start = defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true);

        $response = $next($request);

        $appMs = (microtime(true) - $start) * 1000;
        $response->headers->set('Server-Timing', sprintf(
            'app;dur=%.1f, db;dur=%.1f;desc="%d queries", q;desc="%d"',
            $appMs, self::$dbMs, self::$queries, self::$queries,
        ));

        if (config('core.server_timing_log')) {
            self::log($request, $response, $appMs);
        }

        return $response;
    }

    private static function log(Request $request, Response $response, float $appMs): void
    {
        // Livewire: which component methods / property updates the request carried
        $calls = [];
        foreach ((array) $request->input('components', []) as $component) {
            $snapshot = json_decode((string) ($component['snapshot'] ?? ''), true);
            $name = is_array($snapshot) ? (string) ($snapshot['memo']['name'] ?? '?') : '?';
            foreach ((array) ($component['calls'] ?? []) as $call) {
                $calls[] = $name.'::'.($call['method'] ?? '?');
            }
            foreach (array_keys((array) ($component['updates'] ?? [])) as $property) {
                $calls[] = $name.'.'.$property;
            }
        }

        $line = sprintf(
            "%s\t%s\t%s\t%d\t%.1f\t%.1f\t%d\t%.1f\t%s\n",
            now()->format('H:i:s.v'),
            $request->method(),
            '/'.ltrim($request->path(), '/'),
            $response->getStatusCode(),
            $appMs,
            self::$dbMs,
            self::$queries,
            memory_get_peak_usage(true) / 1048576,
            implode(' ', $calls),
        ).implode('', array_map(fn (string $sql): string => "\t\t".$sql."\n", self::$sql));
        @file_put_contents(storage_path('logs/server-timing.log'), $line, FILE_APPEND | LOCK_EX);
    }
}
