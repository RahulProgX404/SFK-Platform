<?php

declare(strict_types=1);

namespace Modules\Core\Application\Cache;

use Illuminate\Http\Request;
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
 */
final class CacheVersions
{
    public const REFERENCE = 'reference';

    public const DATA = 'data';

    /** @var WeakMap<Request, array<string, string>>|null */
    private static ?WeakMap $perRequest = null;

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
    }

    /** @return array<string, string> */
    private static function all(): array
    {
        /** @return array<string, string> */
        $read = static function (): array {
            try {
                $versions = [];
                foreach (DB::table('cache_versions')->get(['name', 'version']) as $row) {
                    $versions[(string) $row->name] = (string) $row->version;
                }

                return $versions;
            } catch (Throwable) {
                return []; // before the migration
            }
        };

        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return $read();
        }

        self::$perRequest ??= new WeakMap;

        return self::$perRequest[request()] ??= $read();
    }
}
