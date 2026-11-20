<?php

declare(strict_types=1);

namespace Modules\Core\Application\Cache;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

/**
 * The versions of the cached data (table cache_versions): REFERENCE (ReferenceCache — registry, access rights, 一覧,
 * 帳票) and DATA (DataCache — the dashboard's figures). Cached values are keyed with them, so a new version makes every
 * screen read afresh. They are kept in the shared database because local development and Render share it but not their
 * caches. Both are read with one query, once per HTTP request (console processes read them each time).
 *
 * bump() writes a new version. Inside a transaction other connections see it only once the transaction commits — with
 * the data that made it necessary — so nobody caches old data under the new version.
 *
 * Speed (performance pass 2026-11-20, docs/performance.md): that one query is a round trip on every request and every
 * Livewire click (~80 ms from Japan to Neon), so HTTP requests read the versions from the cache store (Redis) — not
 * where the store is the database itself (Render: nothing to gain) or the reference cache is off (tests). The bumper
 * writes the new versions there once its transaction committed, so its own environment sees them on the next request;
 * a request that missed the store fills it only if nobody did meanwhile (Cache::add), so it never puts back what a
 * bump replaced. The store keeps them STORE_SECONDS: what another environment sharing the database (Render ⇄ local
 * development) bumped, or a bump racing another one, is seen at most that much later.
 */
final class CacheVersions
{
    public const REFERENCE = 'reference';

    public const DATA = 'data';

    /** How long HTTP requests take the versions from the cache store before asking the database again. */
    public const STORE_SECONDS = 10;

    private const STORE_KEY = 'sfk:cache-versions';

    /** @var WeakMap<Request, array<string, string>>|null */
    private static ?WeakMap $perRequest = null;

    /** @var WeakMap<Request, true>|null requests that bumped: they read the database (their own, maybe uncommitted, versions) */
    private static ?WeakMap $bumped = null;

    /** The version of `$name` ('-' while it was never bumped or the table is missing). */
    public static function get(string $name): string
    {
        return self::all()[$name] ?? '-';
    }

    public static function bump(string $name): void
    {
        try {
            DB::table('cache_versions')->upsert(
                [['name' => $name, 'version' => (string) Str::ulid(), 'updated_at' => now()]],
                ['name'],
                ['version', 'updated_at'],
            );
        } catch (Throwable) {
            return; // before the migration
        }
        self::$perRequest = null;
        if (! app()->runningInConsole() || app()->runningUnitTests()) {
            self::$bumped ??= new WeakMap;
            self::$bumped[request()] = true;
        }

        if (self::usesStore()) {
            // once committed (others see the new version only then): this environment reads it on the next request
            DB::afterCommit(static function (): void {
                try {
                    Cache::put(self::STORE_KEY, self::read(), self::STORE_SECONDS);
                } catch (Throwable) {
                    // the store is only a shortcut: requests read the database again after STORE_SECONDS
                }
            });
        }
    }

    /** @return array<string, string> */
    private static function all(): array
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return self::read();
        }

        self::$perRequest ??= new WeakMap;

        return self::$perRequest[request()] ??= self::fromStore();
    }

    /** @return array<string, string> */
    private static function fromStore(): array
    {
        if (! self::usesStore() || isset(self::$bumped[request()])) {
            return self::read();
        }

        try {
            $stored = Cache::get(self::STORE_KEY);
            if (is_array($stored)) {
                return array_map('strval', $stored);
            }
            $versions = self::read();
            // only if no bump wrote newer versions meanwhile
            Cache::add(self::STORE_KEY, $versions, self::STORE_SECONDS);

            return $versions;
        } catch (Throwable) {
            return self::read();
        }
    }

    /** Whether HTTP requests take the versions from the cache store (not when that store is the database itself). */
    private static function usesStore(): bool
    {
        return ReferenceCache::enabled() && config('cache.stores.'.config('cache.default').'.driver') !== 'database';
    }

    /** @return array<string, string> */
    private static function read(): array
    {
        try {
            $versions = [];
            foreach (DB::table('cache_versions')->get(['name', 'version']) as $row) {
                $versions[(string) $row->name] = (string) $row->version;
            }

            return $versions;
        } catch (Throwable) {
            return []; // before the migration
        }
    }
}
