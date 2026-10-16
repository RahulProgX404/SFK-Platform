<?php

declare(strict_types=1);

namespace Modules\Core\Domain\Filters;

/**
 * One row of 条件 in the 絞り込む dialog: field, comparison and value(s). Text / number: values[0] (OneOf: every
 * value); choice / user: the chosen options ("" = blank, "--"); date: mode + values[0] = Y-m-d, time = H:i, sub.
 */
final readonly class FilterCondition
{
    public const MAX_KEYWORD_LENGTH = 100;

    public const MAX_VALUES = 100;

    /** @param  list<string>  $values */
    public function __construct(
        public string $field,
        public FilterFieldType $type,
        public FilterOperator $operator,
        public array $values = [],
        public ?DateMode $mode = null,
        public ?string $time = null,
        public ?string $sub = null,
    ) {}

    /** Builds a condition from dialog input; null when the field, comparison or value is not valid. */
    public static function fromArray(mixed $data, FilterFieldType $type): ?self
    {
        if (! is_array($data) || ! is_string($data['field'] ?? null)) {
            return null;
        }

        $operator = FilterOperator::tryFrom(is_string($data['operator'] ?? null) ? $data['operator'] : '');
        if ($operator === null || ! in_array($operator, $type->operators(), true)) {
            return null;
        }

        $values = array_slice(array_values(array_filter(
            is_array($data['values'] ?? null) ? $data['values'] : [],
            fn (mixed $value): bool => is_string($value) || is_int($value),
        )), 0, self::MAX_VALUES);
        $values = array_map(fn (string|int $value): string => mb_substr(trim((string) $value), 0, self::MAX_KEYWORD_LENGTH), $values);

        if (! $operator->needsValue()) {
            return new self($data['field'], $type, $operator);
        }

        if ($type->isDate()) {
            $mode = DateMode::tryFrom(is_string($data['mode'] ?? null) ? $data['mode'] : '') ?? DateMode::Specified;
            $time = is_string($data['time'] ?? null) && $data['time'] !== '' ? $data['time'] : null;
            $sub = is_string($data['sub'] ?? null) && $data['sub'] !== '' ? $data['sub'] : null;
            $values = array_slice($values, 0, 1);

            if ($mode === DateMode::Specified && ($values === [] || $values[0] === '')) {
                return null;
            }

            return new self($data['field'], $type, $operator, $values, $mode, $time, $sub);
        }

        if ($type === FilterFieldType::Choice || $type === FilterFieldType::User) {
            $values = array_values(array_unique($values));

            return $values === [] ? null : new self($data['field'], $type, $operator, $values);
        }

        if ($operator === FilterOperator::OneOf) {
            $values = array_values(array_unique(array_filter($values, fn (string $value): bool => $value !== '')));

            return $values === [] ? null : new self($data['field'], $type, $operator, $values);
        }

        $value = $values[0] ?? '';
        if ($type === FilterFieldType::Number && $value !== '' && ! is_numeric($value)) {
            return null;
        }
        if (($operator === FilterOperator::Contains || $operator === FilterOperator::NotContains || $type === FilterFieldType::Number) && $value === '') {
            return null;
        }

        return new self($data['field'], $type, $operator, [$value]);
    }

    /** @return array{field: string, operator: string, values: list<string>, mode: string|null, time: string|null, sub: string|null} */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'operator' => $this->operator->value,
            'values' => $this->values,
            'mode' => $this->mode?->value,
            'time' => $this->time,
            'sub' => $this->sub,
        ];
    }
}
