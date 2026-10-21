<?php

declare(strict_types=1);

namespace Modules\Core\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * 変更履歴 of a record with related data: besides its columns (RecordsHistory), the users of its ユーザー選択 fields
 * (many-to-many relations named by historyUsers(), kept as user ids) and the rows of its table fields (has-many
 * relations named by historyTables() with the row columns kept). Restoring writes all of it back; afterHistoryRestore()
 * then refreshes what is derived from it.
 *
 * @mixin Model
 */
trait RecordsHistoryWithRelations
{
    use RecordsHistory {
        historySnapshot as private columnSnapshot;
        historyPreview as private columnPreview;
        restoreHistorySnapshot as private restoreColumns;
    }

    /** @return list<string> relations to users kept in the 変更履歴 */
    protected function historyUsers(): array
    {
        return [];
    }

    /** @return array<string, list<string>> table relation => row columns kept in the 変更履歴 */
    protected function historyTables(): array
    {
        return [];
    }

    /** After a version was restored (e.g. recalculate totals of the rows). */
    protected function afterHistoryRestore(): void {}

    /** @return array<string, mixed> */
    public function historySnapshot(): array
    {
        $snapshot = $this->columnSnapshot();
        foreach ($this->historyUsers() as $relation) {
            $snapshot[$relation] = $this->userRelation($relation)->pluck('users.id')->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
        }
        foreach ($this->historyTables() as $relation => $columns) {
            $snapshot[$relation] = $this->rowRelation($relation)->get()
                ->map(fn (Model $row): array => array_intersect_key($row->attributesToArray(), array_flip($columns)))
                ->all();
        }
        ksort($snapshot);

        return $snapshot;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function historyPreview(array $snapshot): Model
    {
        $copy = $this->columnPreview($snapshot);
        foreach ($this->historyUsers() as $relation) {
            $copy->setRelation($relation, User::query()->whereIn('id', (array) ($snapshot[$relation] ?? []))->orderBy('name')->get());
        }
        foreach ($this->historyTables() as $relation => $columns) {
            $model = $this->rowRelation($relation)->getRelated();
            $copy->setRelation($relation, new Collection(array_map(
                fn (mixed $row): Model => $model->newInstance()->forceFill(array_intersect_key((array) $row, array_flip($columns))),
                (array) ($snapshot[$relation] ?? []),
            )));
        }

        return $copy;
    }

    /** @param  array<string, mixed>  $snapshot */
    public function restoreHistorySnapshot(array $snapshot): void
    {
        DB::transaction(function () use ($snapshot): void {
            $this->restoreColumns($snapshot);
            foreach ($this->historyUsers() as $relation) {
                $this->userRelation($relation)->sync(User::query()->whereIn('id', (array) ($snapshot[$relation] ?? []))->pluck('id')->all());
                $this->unsetRelation($relation);
            }
            foreach ($this->historyTables() as $relation => $columns) {
                $rows = $this->rowRelation($relation);
                $rows->get()->each(fn (Model $row) => $row->delete());
                foreach (array_values((array) ($snapshot[$relation] ?? [])) as $sort => $row) {
                    $rows->create([...array_intersect_key((array) $row, array_flip($columns)), 'sort' => $sort]);
                }
                $this->unsetRelation($relation);
            }
            $this->afterHistoryRestore();
        });
    }

    /** @return BelongsToMany<User, Model> */
    private function userRelation(string $relation): BelongsToMany
    {
        $query = $this->{$relation}();
        if (! $query instanceof BelongsToMany) {
            throw new LogicException("[{$relation}] is not a relation to users.");
        }

        return $query;
    }

    /** @return HasMany<Model, Model> */
    private function rowRelation(string $relation): HasMany
    {
        $query = $this->{$relation}();
        if (! $query instanceof HasMany) {
            throw new LogicException("[{$relation}] is not a table of rows.");
        }

        return $query;
    }
}
