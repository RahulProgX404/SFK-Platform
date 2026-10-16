<?php

declare(strict_types=1);

namespace Modules\Core\Application\Filters;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterFieldType;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Filters\RecordFilter;

/**
 * Applies a 絞り込む filter to the query of an app list. Like the legacy system, keyword comparisons ignore case,
 * 次のキーワードを含まない / ≠ keep blank values, "--" (blank) matches null and "", and ログインユーザー is the
 * signed-in user. A condition whose period cannot be resolved (e.g. 31日 in a 30-day month) matches nothing.
 */
final class RecordFilterQuery
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, FilterField>  $fields  by name
     * @return Builder<TModel>
     */
    public static function filter(Builder $query, RecordFilter $filter, array $fields, ?int $userId, CarbonImmutable $now): Builder
    {
        if (! $filter->hasConditions()) {
            return $query;
        }

        return $query->where(function (Builder $group) use ($filter, $fields, $userId, $now): void {
            foreach ($filter->conditions as $condition) {
                $field = $fields[$condition->field] ?? null;
                if ($field === null) {
                    continue;
                }

                $group->where(
                    fn (Builder $query) => self::condition($query, $field, $condition, $userId, $now),
                    boolean: $filter->match === RecordFilter::MATCH_ANY ? 'or' : 'and',
                );
            }
        });
    }

    /**
     * The ソート keys, in order (fields not offered for sorting are skipped).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, FilterField>  $fields
     * @return Builder<TModel>
     */
    public static function sort(Builder $query, RecordFilter $filter, array $fields): Builder
    {
        foreach ($filter->sorts as $sort) {
            $field = $fields[$sort['field']] ?? null;
            if ($field !== null && $field->isSortable()) {
                $query->orderBy($query->qualifyColumn($field->getColumn()), $sort['direction']);
            }
        }

        return $query;
    }

    /** @param  Builder<*>  $query */
    private static function condition(Builder $query, FilterField $field, FilterCondition $condition, ?int $userId, CarbonImmutable $now): void
    {
        if (($custom = $field->getQuery()) !== null) {
            $custom($query, $condition);

            return;
        }

        $values = $condition->type === FilterFieldType::User
            ? array_map(fn (string $value): string => $value === FilterField::LOGIN_USER ? (string) ($userId ?? 0) : $value, $condition->values)
            : $condition->values;

        if (($relationship = $field->getRelationship()) !== null) {
            self::relationship($query, $relationship, (string) $field->getRelatedColumn(), $condition->operator, $values);

            return;
        }

        $column = $query->qualifyColumn($field->getColumn());

        if ($condition->type->isDate()) {
            self::date($query, $column, $condition, $now);

            return;
        }

        $value = $values[0] ?? '';

        match ($condition->operator) {
            FilterOperator::Contains => $query->whereLike($column, '%'.$value.'%'),
            FilterOperator::NotContains => $query->where(fn (Builder $q) => $q->whereNull($column)->orWhereNotLike($column, '%'.$value.'%')),
            FilterOperator::Equals => $value === ''
                ? self::blank($query, $column)
                : $query->where($column, $value),
            FilterOperator::NotEquals => $value === ''
                ? self::notBlank($query, $column)
                : $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, '!=', $value)),
            FilterOperator::OneOf => $query->whereIn($column, $values),
            FilterOperator::LessOrEqual => $query->where($column, '<=', $value),
            FilterOperator::GreaterOrEqual => $query->where($column, '>=', $value),
            FilterOperator::Less => $query->where($column, '<', $value),
            FilterOperator::Greater => $query->where($column, '>', $value),
            FilterOperator::In => self::in($query, $column, $values, $condition->type === FilterFieldType::User),
            FilterOperator::NotIn => self::notIn($query, $column, $values, $condition->type === FilterFieldType::User),
            FilterOperator::Blank => self::blank($query, $column),
            FilterOperator::NotBlank => self::notBlank($query, $column),
        };
    }

    /** @param  Builder<*>  $query */
    private static function date(Builder $query, string $column, FilterCondition $condition, CarbonImmutable $now): void
    {
        $period = $condition->mode?->period($now, $condition->values[0] ?? null, $condition->time, $condition->sub);

        if ($period === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $format = $condition->type === FilterFieldType::Date ? 'Y-m-d' : 'Y-m-d H:i:s';
        [$start, $end] = [$period[0]->format($format), $period[1]->format($format)];

        match ($condition->operator) {
            FilterOperator::Equals => $query->where($column, '>=', $start)->where($column, '<', $end),
            FilterOperator::NotEquals => $query->where(fn (Builder $q) => $q->where($column, '<', $start)->orWhere($column, '>=', $end)),
            FilterOperator::LessOrEqual => $query->where($column, '<', $end),
            FilterOperator::Less => $query->where($column, '<', $start),
            FilterOperator::GreaterOrEqual => $query->where($column, '>=', $start),
            FilterOperator::Greater => $query->where($column, '>=', $end),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /**
     * @param  Builder<*>  $query
     * @param  list<string>  $values
     * @param  bool  $key  a key column (user id): blank is null only
     */
    private static function in(Builder $query, string $column, array $values, bool $key): void
    {
        $chosen = array_values(array_filter($values, fn (string $value): bool => $value !== ''));

        $query->where(function (Builder $q) use ($column, $values, $chosen, $key): void {
            if ($chosen !== []) {
                $q->whereIn($column, $chosen);
            }
            if (in_array('', $values, true)) {
                $key ? $q->orWhereNull($column) : $q->orWhere(fn (Builder $blank) => self::blank($blank, $column));
            }
        });
    }

    /**
     * @param  Builder<*>  $query
     * @param  list<string>  $values
     * @param  bool  $key  a key column (user id): blank is null only
     */
    private static function notIn(Builder $query, string $column, array $values, bool $key): void
    {
        $chosen = array_values(array_filter($values, fn (string $value): bool => $value !== ''));

        if (in_array('', $values, true)) {
            $key ? $query->whereNotNull($column) : self::notBlank($query, $column);
            if ($chosen !== []) {
                $query->whereNotIn($column, $chosen);
            }

            return;
        }

        $query->where(fn (Builder $q) => $q->whereNull($column)->orWhereNotIn($column, $chosen));
    }

    /**
     * @param  Builder<*>  $query
     * @param  list<string>  $values
     */
    private static function relationship(Builder $query, string $relationship, string $column, FilterOperator $operator, array $values): void
    {
        $chosen = array_values(array_filter($values, fn (string $value): bool => $value !== ''));
        $blank = in_array('', $values, true);
        $related = fn (Builder $q) => $q->whereIn($q->qualifyColumn($column), $chosen);

        if ($operator === FilterOperator::NotIn) {
            $query->whereDoesntHave($relationship, $related);
            if ($blank) {
                $query->whereHas($relationship);
            }

            return;
        }

        $query->where(function (Builder $q) use ($relationship, $related, $chosen, $blank): void {
            if ($chosen !== []) {
                $q->whereHas($relationship, $related);
            }
            if ($blank) {
                $q->orWhereDoesntHave($relationship);
            }
        });
    }

    /** @param  Builder<*>  $query */
    private static function blank(Builder $query, string $column): void
    {
        $query->where(fn (Builder $q) => $q->whereNull($column)->orWhere($column, ''));
    }

    /** @param  Builder<*>  $query */
    private static function notBlank(Builder $query, string $column): void
    {
        $query->whereNotNull($column)->where($column, '!=', '');
    }
}
