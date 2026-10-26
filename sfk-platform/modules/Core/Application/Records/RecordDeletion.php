<?php

declare(strict_types=1);

namespace Modules\Core\Application\Records;

use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Core\Filament\AppResource;

/**
 * Deleting a record from any screen. The parts of a record (its rows, 変更履歴, コメント, sub-tables) go with it — their
 * foreign keys cascade. A record other records still refer to through a blocking foreign key (e.g. a 取引先 used by
 * orders and sales, a 機械 used by daily reports) is not deleted: blocker() names what uses it, so the screens can say
 * so instead of failing.
 */
final class RecordDeletion
{
    /** @var array<string, list<array{0: string, 1: string}>>|null referenced table => [[referencing table, column]] */
    private ?array $blocking = null;

    /** @var array<string, string>|null table => app name */
    private ?array $apps = null;

    /** Why the record cannot be deleted (what still uses it), or null when it can. */
    public function blocker(Model $record): ?string
    {
        $uses = [];
        foreach ($this->blockingKeys()[$record->getTable()] ?? [] as [$table, $column]) {
            $count = DB::table($table)->where($column, $record->getKey())->count();
            if ($count > 0) {
                $label = $this->label($table);
                $uses[$label] = ($uses[$label] ?? 0) + $count;
            }
        }

        if ($uses === []) {
            return null;
        }

        $list = implode('、', array_map(fn (string $label, int $count): string => $label.'（'.$count.'件）', array_keys($uses), $uses));

        return 'このレコードは次で使われているため削除できません：'.$list;
    }

    /** Deletes the record with its parts; returns why it was not deleted, or null when it was. */
    public function delete(Model $record): ?string
    {
        if (($reason = $this->blocker($record)) !== null) {
            return $reason;
        }

        try {
            DB::transaction(fn () => $record->delete());
        } catch (QueryException $exception) {
            // a reference the check above could not see (e.g. added by a hook while deleting)
            if (self::isForeignKeyViolation($exception)) {
                return 'このレコードは他のレコードで使われているため削除できません。';
            }

            throw $exception;
        }

        return null;
    }

    private static function isForeignKeyViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23503' || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed');
    }

    /**
     * The foreign keys that block deleting the rows they point to (restrict / no action), by referenced table; read
     * from the schema once per migration state.
     *
     * @return array<string, list<array{0: string, 1: string}>>
     */
    private function blockingKeys(): array
    {
        if ($this->blocking !== null) {
            return $this->blocking;
        }

        $state = Schema::hasTable('migrations') ? (string) DB::table('migrations')->max('id') : '0';

        return $this->blocking = Cache::remember('core:blocking-foreign-keys:'.DB::getDefaultConnection().':'.$state, now()->addDay(), function (): array {
            $blocking = [];
            foreach (array_column(Schema::getTables(), 'name') as $table) {
                foreach (Schema::getForeignKeys($table) as $key) {
                    if (in_array(strtolower((string) $key['on_delete']), ['restrict', 'no action'], true) && count($key['columns']) === 1) {
                        $blocking[$key['foreign_table']][] = [$table, $key['columns'][0]];
                    }
                }
            }

            return $blocking;
        });
    }

    /** The app a table belongs to (its records, or the rows of its records), else 関連データ. */
    private function label(string $table): string
    {
        $apps = $this->apps();
        if (isset($apps[$table])) {
            return $apps[$table];
        }

        // rows of a record (e.g. order_papers of an order): the app of the record they belong to
        foreach (Schema::getForeignKeys($table) as $key) {
            if (strtolower((string) $key['on_delete']) === 'cascade' && isset($apps[$key['foreign_table']])) {
                return $apps[$key['foreign_table']];
            }
        }

        return '関連データ';
    }

    /** @return array<string, string> table => app name, from the app screens of every dashboard */
    private function apps(): array
    {
        if ($this->apps !== null) {
            return $this->apps;
        }

        $this->apps = [];
        foreach (Filament::getPanels() as $panel) {
            foreach ($panel->getResources() as $resource) {
                if (is_subclass_of($resource, AppResource::class)) {
                    $model = $resource::getModel();
                    $this->apps[(new $model)->getTable()] ??= $resource::appName();
                }
            }
        }

        return $this->apps;
    }
}
