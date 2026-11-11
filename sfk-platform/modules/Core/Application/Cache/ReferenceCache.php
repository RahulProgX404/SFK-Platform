<?php

declare(strict_types=1);

namespace Modules\Core\Application\Cache;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Speed (user, 2026-11-11 — "pages must not feel like loading"): the data every screen needs before it shows anything —
 * the app registry with its spaces, who may open which dashboard / space / app (アクセス権, フィールド, レコード), 一覧 and
 * 帳票 — is read from the cache (Redis; the database cache on Render) instead of a dozen round trips to the database
 * (each ~80 ms from Japan to Neon) on every page and every Livewire request.
 *
 * One version covers all of it (CacheVersions::REFERENCE, kept in the shared database so that a change made on Render is
 * seen locally too and the other way round): any change of those tables bumps it (model events in CoreServiceProvider,
 * and bump() where they are written without events — pivot syncs, bulk deletes), so the next request reads them afresh.
 * The TTL is only a safety net.
 * Off in the tests (phpunit*.xml: SFK_REFERENCE_CACHE=false), where each "request" must see what the test just wrote.
 */
final class ReferenceCache
{
    /** Safety net: cached data lives at most this long even if a change was missed. */
    public const TTL_SECONDS = 600;

    /**
     * @template T
     *
     * @param  Closure(): T  $compute
     * @return T
     */
    public static function remember(string $key, Closure $compute): mixed
    {
        if (! self::enabled()) {
            return $compute();
        }

        // wrapped: the cache cannot tell a stored null from a missing key
        return Cache::remember('sfk:ref2:'.self::version().':'.$key, self::TTL_SECONDS, fn (): array => ['value' => $compute()])['value'];
    }

    /**
     * Eloquent models (with the given relations loaded) through the cache. They are kept as plain column values and
     * built again on reading: the cache unserializes no PHP objects (config cache.serializable_classes = false).
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $class
     * @param  Closure(): iterable<TModel>  $query
     * @param  list<string>  $relations  relations of the models to keep (one level)
     * @return EloquentCollection<int, TModel>
     */
    public static function rememberModels(string $key, string $class, Closure $query, array $relations = []): EloquentCollection
    {
        if (! self::enabled()) {
            return new EloquentCollection(is_array($rows = $query()) ? $rows : iterator_to_array($rows, false));
        }

        $rows = self::remember($key, function () use ($query, $relations): array {
            $rows = [];
            foreach ($query() as $model) {
                $loaded = [];
                foreach ($relations as $relation) {
                    $value = $model->getRelation($relation);
                    $loaded[$relation] = $value instanceof Collection
                        ? ['many' => true, 'rows' => $value->map(fn (Model $related): array => $related->getAttributes())->values()->all()]
                        : ['many' => false, 'rows' => $value instanceof Model ? $value->getAttributes() : null];
                }
                $rows[] = ['attributes' => $model->getAttributes(), 'relations' => $loaded];
            }

            return $rows;
        });

        $prototype = new $class;
        $models = [];
        foreach ($rows as $row) {
            $model = $prototype->newFromBuilder($row['attributes']);
            foreach ($row['relations'] as $relation => $value) {
                $related = $model->{$relation}()->getRelated();
                $model->setRelation($relation, $value['many']
                    ? $related->newCollection(array_map(fn (array $attributes): Model => $related->newFromBuilder($attributes), $value['rows']))
                    : ($value['rows'] === null ? null : $related->newFromBuilder($value['rows'])));
            }
            $models[] = $model;
        }

        return $prototype->newCollection($models);
    }

    /** Something cached here changed: every key is read afresh from the next request on. */
    public static function bump(): void
    {
        if (self::enabled()) {
            CacheVersions::bump(CacheVersions::REFERENCE);
        }
    }

    public static function enabled(): bool
    {
        return (bool) config('core.reference_cache', true);
    }

    private static function version(): string
    {
        return CacheVersions::get(CacheVersions::REFERENCE);
    }
}
