<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Filters;

/**
 * The content of the 絞り込む dialog of an app list: 条件 combined by すべての条件を満たす (and) or いずれかの条件を満たす
 * (or), and ソート keys. Applied for the session (適用) or stored as a list view (保存).
 */
final readonly class RecordFilter
{
    public const MATCH_ALL = 'and';

    public const MATCH_ANY = 'or';

    public const MAX_CONDITIONS = 30;

    public const MAX_SORTS = 5;

    /**
     * @param  list<FilterCondition>  $conditions
     * @param  list<array{field: string, direction: 'asc'|'desc'}>  $sorts
     */
    public function __construct(
        public string $match = self::MATCH_ALL,
        public array $conditions = [],
        public array $sorts = [],
    ) {}

    /**
     * Builds the filter from dialog input, dropping rows with unknown fields or incomplete values.
     *
     * @param  array<string, FilterFieldType>  $types  filterable fields
     * @param  list<string>  $sortable  fields offered under ソート
     */
    public static function fromArray(mixed $data, array $types, array $sortable): self
    {
        if (! is_array($data)) {
            return new self;
        }

        $conditions = [];
        foreach (array_slice(is_array($data['conditions'] ?? null) ? array_values($data['conditions']) : [], 0, self::MAX_CONDITIONS) as $row) {
            $field = is_array($row) && is_string($row['field'] ?? null) ? $row['field'] : '';
            if (isset($types[$field]) && ($condition = FilterCondition::fromArray($row, $types[$field])) !== null) {
                $conditions[] = $condition;
            }
        }

        $sorts = [];
        foreach (is_array($data['sorts'] ?? null) ? array_values($data['sorts']) : [] as $row) {
            $field = is_array($row) && is_string($row['field'] ?? null) ? $row['field'] : '';
            if (in_array($field, $sortable, true) && ! in_array($field, array_column($sorts, 'field'), true)) {
                $sorts[] = ['field' => $field, 'direction' => ($row['direction'] ?? null) === 'desc' ? 'desc' : 'asc'];
            }
        }

        return new self(
            ($data['match'] ?? null) === self::MATCH_ANY ? self::MATCH_ANY : self::MATCH_ALL,
            $conditions,
            array_slice($sorts, 0, self::MAX_SORTS),
        );
    }

    public function hasConditions(): bool
    {
        return $this->conditions !== [];
    }

    public function isEmpty(): bool
    {
        return $this->conditions === [] && $this->sorts === [];
    }

    /** @return array{match: string, conditions: list<array<string, mixed>>, sorts: list<array{field: string, direction: string}>} */
    public function toArray(): array
    {
        return [
            'match' => $this->match,
            'conditions' => array_map(fn (FilterCondition $condition): array => $condition->toArray(), $this->conditions),
            'sorts' => $this->sorts,
        ];
    }
}
