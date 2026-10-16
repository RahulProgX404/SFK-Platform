<?php

declare(strict_types=1);

namespace Modules\Core\Application\Views;

use Modules\Core\Domain\Views\ListViewType;
use Modules\Core\Models\AppListView;

/**
 * One 一覧 of an app list: a stored view (AppListView), one of the app's code-defined defaults (until they are stored,
 * see ListViewService) or the built-in （すべて） (every column, no conditions; cannot be moved, edited, copied or
 * deleted). `filter` is Modules\Core\Domain\Filters\RecordFilter::toArray().
 */
final readonly class ListView
{
    public const ALL = 'all';

    public const ALL_NAME = '（すべて）';

    /**
     * @param  list<string>  $columns
     * @param  array<string, mixed>  $filter
     */
    public function __construct(
        public string $key,
        public string $name,
        public ListViewType $type = ListViewType::Table,
        public array $columns = [],
        public array $filter = [],
        public ?string $dateField = null,
        public ?string $titleField = null,
        public ?int $id = null,
    ) {}

    /**
     * A 表形式 default view of an app (AppListRecords::defaultListViews()).
     *
     * @param  list<string>  $columns
     * @param  list<array{field: string, direction: 'asc'|'desc'}>  $sorts
     * @param  list<array<string, mixed>>  $conditions
     */
    public static function table(string $key, string $name, array $columns, array $sorts = [], array $conditions = [], string $match = 'and'): self
    {
        return new self($key, $name, ListViewType::Table, $columns, ['match' => $match, 'conditions' => $conditions, 'sorts' => $sorts]);
    }

    /**
     * A カレンダー形式 default view of an app.
     *
     * @param  list<array<string, mixed>>  $conditions
     */
    public static function calendar(string $key, string $name, string $dateField, string $titleField, array $conditions = []): self
    {
        return new self($key, $name, ListViewType::Calendar, [], ['match' => 'and', 'conditions' => $conditions, 'sorts' => []], $dateField, $titleField);
    }

    /** @param  list<string>  $columns  every column of the app */
    public static function all(array $columns): self
    {
        return new self(self::ALL, self::ALL_NAME, ListViewType::Table, $columns);
    }

    public static function fromModel(AppListView $view): self
    {
        return new self(
            (string) $view->id,
            $view->name,
            $view->type,
            array_values(array_filter($view->columns ?? [], 'is_string')),
            $view->filter,
            $view->date_field,
            $view->title_field,
            $view->id,
        );
    }

    public function isBuiltIn(): bool
    {
        return $this->key === self::ALL;
    }

    public function isCalendar(): bool
    {
        return $this->type === ListViewType::Calendar;
    }

    /** @return array{key: string, id: int|null, name: string, type: string, columns: list<string>, filter: array<string, mixed>, dateField: string|null, titleField: string|null} */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type->value,
            'columns' => $this->columns,
            'filter' => $this->filter,
            'dateField' => $this->dateField,
            'titleField' => $this->titleField,
        ];
    }
}
