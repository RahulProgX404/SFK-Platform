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
 * so instead of failing. impact() tells the delete confirmations what deleting does to the records of other apps.
 */
final class RecordDeletion
{
    /** @var list<array{table: string, column: string, foreign_table: string, on_delete: string}>|null */
    private ?array $keys = null;

    /** @var array<string, string>|null table => app name */
    private ?array $apps = null;

    /** Why the record cannot be deleted (what still uses it), or null when it can. */
    public function blocker(Model $record): ?string
    {
        $uses = [];
        $deletesWith = self::deletesWith($record);
        foreach ($this->referencesTo($record->getTable()) as $key) {
            if ($key['on_delete'] !== 'restrict' || ($deletesWith[$key['table']] ?? null) === $key['column']) {
                continue;
            }

            $count = DB::table($key['table'])->where($key['column'], $record->getKey())->count();
            if ($count > 0) {
                $label = $this->label($key['table']);
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

    /**
     * What deleting the records does to the records of other apps, for the warning of the delete confirmations — by
     * app: records deleted with them (cascading keys), rows of other apps' records deleted with them (DeletesWith),
     * records whose reference to them is emptied (set null), and records that keep them from being deleted (blocking
     * keys). The records' own parts (rows, sub-tables, 変更履歴, コメント) are not listed.
     *
     * @param  class-string<Model>|Model  $model
     * @param  list<int|string>  $keys
     * @return list<array{app: string, text: string, blocks: bool}>
     */
    public function impact(string|Model $model, array $keys): array
    {
        $model = is_string($model) ? new $model : $model;
        $apps = $this->apps();
        $deletesWith = self::deletesWith($model);
        $impact = [];

        foreach ($keys === [] ? [] : $this->referencesTo($model->getTable()) as $key) {
            $isApp = isset($apps[$key['table']]);
            $deleted = $key['on_delete'] === 'cascade' || ($deletesWith[$key['table']] ?? null) === $key['column'];
            $blocks = ! $deleted && $key['on_delete'] === 'restrict';
            // rows of another app's records (e.g. 納品書 rows of a 売上); rows cascading with ours are our own parts
            $parent = $isApp ? null : $this->parentKey($key['table'], $model->getTable());
            if (! $isApp && ! $blocks && ($parent === null || $key['on_delete'] === 'cascade')) {
                continue;
            }

            $count = $this->count($key['table'], $key['column'], $keys, $parent['column'] ?? null);
            if ($count === 0) {
                continue;
            }

            $impact[] = [
                'app' => $isApp ? $apps[$key['table']] : ($parent !== null ? $apps[$parent['foreign_table']] : '関連データ'),
                'text' => match (true) {
                    $blocks => $count.'件のレコードで使われています（使われているレコードは削除されません）',
                    $deleted && $isApp => $count.'件のレコードが削除されます',
                    $deleted => $count.'件のレコードから明細が削除されます',
                    $isApp => $count.'件のレコードとの関連付けが解除されます',
                    default => $count.'件のレコードの明細との関連付けが解除されます',
                },
                'blocks' => $blocks,
            ];
        }

        return $impact;
    }

    private static function isForeignKeyViolation(QueryException $exception): bool
    {
        return $exception->getCode() === '23503' || str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed');
    }

    /**
     * Rows referring to the keys; with $distinct, the number of records they belong to.
     *
     * @param  list<int|string>  $keys
     */
    private function count(string $table, string $column, array $keys, ?string $distinct): int
    {
        $count = 0;
        $parents = [];
        foreach (array_chunk($keys, 1000) as $chunk) {
            $query = DB::table($table)->whereIn($column, $chunk);
            if ($distinct === null) {
                $count += $query->count();
            } else {
                $parents += array_flip($query->distinct()->pluck($distinct)->filter()->all());
            }
        }

        return $distinct === null ? $count : count($parents);
    }

    /** @return array<string, string> table => column of the rows the model deletes itself (DeletesWith) */
    private static function deletesWith(Model $model): array
    {
        return $model instanceof DeletesWith ? $model::deletesWith() : [];
    }

    /** @return list<array{table: string, column: string, foreign_table: string, on_delete: string}> */
    private function referencesTo(string $table): array
    {
        return array_values(array_filter($this->foreignKeys(), fn (array $key): bool => $key['foreign_table'] === $table));
    }

    /**
     * The key of a table of rows to the app record they belong to (cascading), other than one to $except.
     *
     * @return array{table: string, column: string, foreign_table: string, on_delete: string}|null
     */
    private function parentKey(string $table, string $except): ?array
    {
        $apps = $this->apps();
        foreach ($this->foreignKeys() as $key) {
            if ($key['table'] === $table && $key['on_delete'] === 'cascade' && $key['foreign_table'] !== $except && isset($apps[$key['foreign_table']])) {
                return $key;
            }
        }

        return null;
    }

    /**
     * The single-column foreign keys of the schema, on_delete as cascade / set null / restrict (restrict and no action
     * block the delete); read from the schema once per migration state.
     *
     * @return list<array{table: string, column: string, foreign_table: string, on_delete: string}>
     */
    private function foreignKeys(): array
    {
        if ($this->keys !== null) {
            return $this->keys;
        }

        $state = Schema::hasTable('migrations') ? (string) DB::table('migrations')->max('id') : '0';

        return $this->keys = Cache::remember('core:foreign-keys:'.DB::getDefaultConnection().':'.$state, now()->addDay(), function (): array {
            $keys = [];
            foreach (array_column(Schema::getTables(), 'name') as $table) {
                foreach (Schema::getForeignKeys($table) as $key) {
                    if (count($key['columns']) !== 1) {
                        continue;
                    }

                    $onDelete = strtolower((string) $key['on_delete']);
                    $keys[] = [
                        'table' => (string) $table,
                        'column' => (string) $key['columns'][0],
                        'foreign_table' => (string) $key['foreign_table'],
                        'on_delete' => in_array($onDelete, ['cascade', 'set null'], true) ? $onDelete : 'restrict',
                    ];
                }
            }

            return $keys;
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
        $parent = $this->parentKey($table, '');

        return $parent !== null ? $apps[$parent['foreign_table']] : '関連データ';
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
