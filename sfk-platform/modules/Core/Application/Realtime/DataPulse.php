<?php

declare(strict_types=1);

namespace Modules\Core\Application\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live pages (user 2026-11-21, 売上分析 / 日報分析; 2026-11-24 every card of the dashboard): when a record of a table such a page shows is saved or deleted —
 * on any screen, by anyone — its open copies redraw themselves without a reload. A module says which tables its live
 * pages read (watch()); Core stamps a watched table in the cache when one of its records changes (CoreServiceProvider
 * listens to every Eloquent model; REFERENCE when the registry or access rights change, ReferenceCache::bump()), and
 * the stamps ride on the pulse every page already asks (PulseChannels 'd'): cache reads only, no database. The page
 * (core::filament.live-data marker, core::filament.live-data-script) compares them with the stamps it was drawn with
 * and refreshes its Livewire component when one moved — within the pulse's few seconds, polling only (docs/realtime.md).
 *
 * Stamps are written after the commit (others then also see the data), once at the end of an HTTP request, a queue
 * job or a command, and at most once a second while a long process saves (a legacy import). The cache is not shared
 * between environments (Render ⇄ local development share the database only): a page sees the changes made where it is
 * served. Writes that do not go through Eloquent (query-builder updates, pivot syncs without their parent) stamp
 * nothing.
 */
final class DataPulse
{
    /** The registry and access rights (who may see what) changed. */
    public const REFERENCE = '@reference';

    /**
     * A channel of one user (`@me:<name>`, e.g. APP_USAGE): stamped for that user alone (touchedBy()), and the pulse
     * and the marker of a page carry the stamp of their own user — one user's doings move no one else's page.
     */
    public const PERSONAL = '@me:';

    /** The apps and records the user opened (AppUsageLog, written without model events): 最近表示した項目. */
    public const APP_USAGE = self::PERSONAL.'app-usage';

    private const KEY = 'sfk:pulse:data:';

    private const TTL_SECONDS = 7 * 86400;

    /** @var array<string, true> */
    private static array $watched = [];

    /** @var array<string, true> */
    private static array $pending = [];

    private static ?float $lastWrite = null;

    /** @param  list<string>  $tables  tables (or REFERENCE) a live page reads */
    public static function watch(array $tables): void
    {
        foreach ($tables as $table) {
            self::$watched[$table] = true;
        }
    }

    /** @return list<string> */
    public static function watched(): array
    {
        return array_keys(self::$watched);
    }

    /** A record of the table was saved or deleted (only watched tables are stamped). */
    public static function touched(string $table): void
    {
        if (! isset(self::$watched[$table]) || str_starts_with($table, self::PERSONAL)) {
            return;
        }

        self::$pending[$table] = true;
        // a long process (queue worker, import): at most once a second while it runs, the rest at its end
        if (app()->runningInConsole() && ! app()->runningUnitTests() && (self::$lastWrite === null || microtime(true) - self::$lastWrite > 1.0)) {
            self::flush();
        }
    }

    /** The end of a request, a queue job or a command (and the throttled writes of a long process). */
    public static function flush(): void
    {
        if (self::$pending === []) {
            return;
        }

        $tables = array_keys(self::$pending);
        self::$pending = [];
        self::$lastWrite = microtime(true);

        DB::afterCommit(static function () use ($tables): void {
            $stamp = (string) Str::ulid();
            try {
                Cache::putMany(array_fill_keys(array_map(fn (string $table): string => self::KEY.$table, $tables), $stamp), self::TTL_SECONDS);
            } catch (Throwable) {
                // the cache is down: open pages keep what they show until the next change
            }
        });
    }

    /**
     * Something of one user's own channel (PERSONAL) changed: stamped at once (after the commit) — such writes are few
     * (one per app screen opened) and may come after the response, past the end-of-request flush.
     */
    public static function touchedBy(string $channel, int $userId): void
    {
        if (! isset(self::$watched[$channel]) || ! str_starts_with($channel, self::PERSONAL)) {
            return;
        }

        DB::afterCommit(static function () use ($channel, $userId): void {
            try {
                Cache::put(self::key($channel, $userId), (string) Str::ulid(), self::TTL_SECONDS);
            } catch (Throwable) {
                // the cache is down: open pages keep what they show until the next change
            }
        });
    }

    /**
     * The stamps of the tables ('' for one never stamped), one cache read; a PERSONAL channel is $userId's own ('' without
     * a user).
     *
     * @param  list<string>  $tables
     * @return array<string, string>
     */
    public static function versions(array $tables, ?int $userId = null): array
    {
        if ($tables === []) {
            return [];
        }

        $keys = [];
        foreach ($tables as $table) {
            $key = self::key($table, $userId);
            if ($key !== null) {
                $keys[$table] = $key;
            }
        }

        try {
            $values = $keys === [] ? [] : Cache::many(array_values($keys));
        } catch (Throwable) {
            return array_fill_keys($tables, '');
        }

        $versions = [];
        foreach ($tables as $table) {
            $versions[$table] = isset($keys[$table]) ? (string) ($values[$keys[$table]] ?? '') : '';
        }

        return $versions;
    }

    /** The cache key of a table's stamp; a PERSONAL channel's is the user's own (null without a user). */
    private static function key(string $table, ?int $userId): ?string
    {
        if (! str_starts_with($table, self::PERSONAL)) {
            return self::KEY.$table;
        }

        return $userId !== null ? self::KEY.$table.':'.$userId : null;
    }
}
