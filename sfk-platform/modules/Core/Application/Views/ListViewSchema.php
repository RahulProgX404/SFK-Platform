<?php

declare(strict_types=1);

namespace Modules\Core\Application\Views;

use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Domain\Filters\FilterFieldType;

/**
 * What a 一覧 of an app can show: its list columns (表形式), its 絞り込む fields (絞り込み, ソート) and, derived from
 * those, the 日付 (date / date-time) and タイトル (text) fields of カレンダー形式. Built by AppListRecords::listViewSchema().
 */
final readonly class ListViewSchema
{
    /**
     * @param  array<string, string>  $columns  column name => label, in the order of （すべて）
     * @param  list<FilterField>  $filterFields
     */
    public function __construct(
        public array $columns,
        public array $filterFields = [],
    ) {}

    /** @return list<string> */
    public function columnNames(): array
    {
        return array_keys($this->columns);
    }

    /** @return array<string, FilterFieldType> */
    public function filterTypes(): array
    {
        $types = [];
        foreach ($this->filterFields as $field) {
            $types[$field->name] = $field->type;
        }

        return $types;
    }

    /** @return list<string> */
    public function sortableFields(): array
    {
        return array_values(array_map(
            fn (FilterField $field): string => $field->name,
            array_filter($this->filterFields, fn (FilterField $field): bool => $field->isSortable()),
        ));
    }

    /** @return array<string, string> カレンダー形式 日付 choices */
    public function dateFields(): array
    {
        return $this->fieldsOf(fn (FilterField $field): bool => $field->type->isDate());
    }

    /** @return array<string, string> カレンダー形式 タイトル choices */
    public function titleFields(): array
    {
        return $this->fieldsOf(fn (FilterField $field): bool => ($field->type === FilterFieldType::Text || $field->type === FilterFieldType::Number)
            && $field->getRelationship() === null && $field->getQuery() === null);
    }

    /**
     * @param  callable(FilterField): bool  $accept
     * @return array<string, string>
     */
    private function fieldsOf(callable $accept): array
    {
        $fields = [];
        foreach ($this->filterFields as $field) {
            if ($accept($field)) {
                $fields[$field->name] = $field->label;
            }
        }

        return $fields;
    }
}
