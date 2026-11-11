<?php

declare(strict_types=1);

namespace Modules\Core\Application\Cache;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Speed (user, 2026-11-11): figures that add up business data — the dashboard's cards — are kept in the cache under the
 * DATA version (CacheVersions, in the shared database) and the REFERENCE version (who may see what). Any record saved or
 * deleted anywhere bumps DATA (CoreServiceProvider listens to every Eloquent model), so the next view adds up afresh;
 * a view with nothing changed is served from the cache. Keys say whose view it is (the viewer's rights apply).
 *
 * Bumps are written at most once a second while records are being saved (a legacy import saves thousands), and once
 * more when the request, the queue job or the command ends if records were saved after the last bump. Writes that do
 * not go through Eloquent (query-builder updates) do not bump: what reads them is not cached here.
 */
final class DataCache
{
    public const TTL_SECONDS = 3600;

    private static ?float $lastBump = null;

    private static bool $pending = false;

    /**
     * @template T
     *
     * @param  Closure(): T  $compute  must return plain values (arrays, strings, numbers): the cache keeps no PHP objects
     * @return T
     */
    public static function remember(string $key, Closure $compute): mixed
    {
        if (! ReferenceCache::enabled()) {
            return $compute();
        }

        // wrapped: the cache cannot tell a stored null from a missing key
        return Cache::remember(
            'sfk:data2:'.CacheVersions::get(CacheVersions::DATA).':'.CacheVersions::get(CacheVersions::REFERENCE).':'.$key,
            self::TTL_SECONDS,
            fn (): array => ['value' => $compute()],
        )['value'];
    }

    /** A record was saved or deleted. */
    public static function changed(): void
    {
        if (! ReferenceCache::enabled()) {
            return;
        }

        $now = microtime(true);
        if (self::$lastBump === null || $now - self::$lastBump > 1.0) {
            self::$lastBump = $now;
            self::$pending = false;
            CacheVersions::bump(CacheVersions::DATA);
        } else {
            self::$pending = true;
        }
    }

    /** The end of a request, a queue job or a command: records saved after the last bump bump once more. */
    public static function flush(): void
    {
        if (self::$pending) {
            self::$pending = false;
            self::$lastBump = microtime(true);
            CacheVersions::bump(CacheVersions::DATA);
        }
    }
}
