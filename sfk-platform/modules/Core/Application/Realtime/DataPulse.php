<?php

declare(strict_types=1);

namespace Modules\Core\Application\Realtime;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Live pages (user 2026-11-21, 売上分析 / 日報分析): when a record of a table such a page shows is saved or deleted —
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
        if (! isset(self::$watched[$table])) {
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
     * The stamps of the tables ('' for one never stamped), one cache read.
     *
     * @param  list<string>  $tables
     * @return array<string, string>
     */
    public static function versions(array $tables): array
    {
        if ($tables === []) {
            return [];
        }

        try {
            $values = Cache::many(array_map(fn (string $table): string => self::KEY.$table, $tables));
        } catch (Throwable) {
            return array_fill_keys($tables, '');
        }

        $versions = [];
        foreach ($tables as $table) {
            $versions[$table] = (string) ($values[self::KEY.$table] ?? '');
        }

        return $versions;
    }
}
