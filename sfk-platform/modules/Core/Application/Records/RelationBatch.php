<?php

declare(strict_types=1);

namespace Modules\Core\Application\Records;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Loads the belongs-to relations of a record — and of its rows, e.g. `papers.supplier` — with ONE query per related
 * table instead of one per relation and row (performance pass 2026-11-20: a 受注 record page read 取引先 five times
 * and on every Livewire request of the page). The relations get exactly what they would have loaded themselves; one
 * with constraints of its own (a where, a removed scope) is left to load as before. The rows of a list page too
 * (loadMany(), AppListRecords::batchedTableRelations(); pass 2026-11-24: 受注 read 取引先 three times per click).
 */
final class RelationBatch
{
    /**
     * @param  list<string>  $paths  relation names, or dotted paths through to-many / to-one relations ("papers.supplier")
     */
    public static function load(Model $record, array $paths): void
    {
        self::loadMany([$record], $paths);
    }

    /**
     * The same for several records (a page of a list): one query per related table for all of them; what cannot be
     * batched (constrained relations) is eager-loaded as usual — one query per relation, never one per record.
     *
     * @param  iterable<Model>  $records
     * @param  list<string>  $paths
     */
    public static function loadMany(iterable $records, array $paths, bool $loadRest = false): void
    {
        $records = array_values(is_array($records) ? $records : iterator_to_array($records, false));
        if ($records === [] || $paths === []) {
            return;
        }

        /** @var array<string, array{related: Model, owner: string, targets: list<array{0: Model, 1: string, 2: mixed}>}> $groups */
        $groups = [];

        foreach ($paths as $path) {
            $segments = explode('.', $path);
            $leaf = (string) array_pop($segments);
            $parents = $records;
            foreach ($segments as $segment) {
                $next = [];
                foreach ($parents as $parent) {
                    $value = $parent->getRelationValue($segment);
                    foreach ($value instanceof EloquentCollection ? $value->all() : [$value] as $model) {
                        if ($model instanceof Model) {
                            $next[] = $model;
                        }
                    }
                }
                $parents = $next;
            }

            foreach ($parents as $parent) {
                if ($parent->relationLoaded($leaf) || ! method_exists($parent, $leaf)) {
                    continue;
                }
                $relation = Relation::noConstraints(fn (): mixed => $parent->{$leaf}());
                if (! $relation instanceof BelongsTo || $relation->getQuery()->getQuery()->wheres !== [] || $relation->getQuery()->removedScopes() !== []) {
                    continue;
                }
                $related = $relation->getRelated();
                $owner = $relation->getOwnerKeyName();
                $key = $related::class.'|'.$owner;
                $groups[$key]['related'] = $related;
                $groups[$key]['owner'] = $owner;
                $groups[$key]['targets'][] = [$parent, $leaf, $parent->getAttribute($relation->getForeignKeyName())];
            }
        }

        foreach ($groups as $group) {
            $ids = array_values(array_unique(array_filter(array_column($group['targets'], 2), fn (mixed $id): bool => $id !== null && $id !== '')));
            $found = $ids === [] ? [] : $group['related']->newQuery()->whereIn($group['owner'], $ids)->get()->keyBy($group['owner'])->all();
            foreach ($group['targets'] as [$parent, $name, $id]) {
                $parent->setRelation($name, $id === null || $id === '' ? null : ($found[$id] ?? null));
            }
        }

        // $loadRest (records of one model, e.g. a list page whose eager loads were taken off): the relations that could
        // not be batched are eager-loaded for all records at once, so none is lazy-loaded row by row
        if ($loadRest) {
            (new EloquentCollection($records))->loadMissing($paths);
        }
    }
}
