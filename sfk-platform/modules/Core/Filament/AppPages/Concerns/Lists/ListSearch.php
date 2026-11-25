<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Lists;

use App\Models\User;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Core\Domain\Filters\FilterCondition;
use Modules\Core\Domain\Filters\FilterFieldType;
use Modules\Core\Domain\Filters\FilterOperator;
use Modules\Core\Domain\Search\SearchText;

/**
 * 検索 of an app list: each word must be found (normalized, SearchQuery) — in the column chosen with the icon right
 * of the box (kept per user and list until logout), else in one of the app's searchable columns.
 *
 * Part of Modules\Core\Filament\AppPages\AppListRecords (the shared app list); uses its views, fields and access rights.
 */
trait ListSearch
{
    /**
     * 検索: the one column searched (the icon right of the search box); null = the app's searchable columns. Kept for
     * the signed-in user until they log out (searchColumns()), so another user starts from the default.
     */
    #[Locked]
    public ?string $searchColumn = null;

    /** Session key of the chosen search columns: user id => list page => column (cleared at login and logout). */
    public const SEARCH_COLUMNS_SESSION = 'sfk_search_columns';

    /**
     * The columns of the current 一覧 the search can look in one at a time (name => label): the app's searchable
     * columns and the fields of 絞り込む (text, number, date, choice, user).
     *
     * @return array<string, string>
     */
    public function searchColumnOptions(?Table $table = null): array
    {
        $searchable = $this->defaultSearchColumns($table);
        $fields = $this->filterFieldMap();
        $labels = $this->listColumns();
        $options = [];
        foreach ($this->viewColumns() as $name) {
            if (in_array($name, $searchable, true) || isset($fields[$name])) {
                $options[$name] = $labels[$name] ?? $name;
            }
        }

        return $options;
    }

    /** @return list<string> the columns the search looks in when no column is chosen (the app's searchable columns) */
    public function defaultSearchColumns(?Table $table = null): array
    {
        $columns = [];
        foreach (($table ?? $this->getTable())->getColumns() as $name => $column) {
            if (! $column->isHidden() && $column->isGloballySearchable()) {
                $columns[] = (string) $name;
            }
        }

        return $columns;
    }

    /** The column chosen for the search, or null for the app's searchable columns (the first column when it has none). */
    public function activeSearchColumn(): ?string
    {
        $options = $this->searchColumnOptions();
        if ($this->searchColumn !== null && isset($options[$this->searchColumn])) {
            return $this->searchColumn;
        }

        return $this->defaultSearchColumns() === [] ? array_key_first($options) : null;
    }

    /** The icon right of the search box: search one column (null = the app's searchable columns). */
    public function setSearchColumn(?string $name): void
    {
        $this->searchColumn = $name !== null && isset($this->searchColumnOptions()[$name]) ? $name : null;
        $this->cancelInlineEdit();
        $this->resetPage($this->getTablePaginationPageName());

        $user = auth()->id();
        if ($user !== null) {
            $stored = (array) session(self::SEARCH_COLUMNS_SESSION, []);
            $stored[$user] = [...self::searchColumns(), static::class => $this->searchColumn];
            session([self::SEARCH_COLUMNS_SESSION => $stored]);
        }
    }

    /** @return array<string, string|null> the signed-in user's chosen search columns, by list page */
    private static function searchColumns(): array
    {
        $user = auth()->id();
        $stored = (array) session(self::SEARCH_COLUMNS_SESSION, []);

        return $user !== null && is_array($stored[$user] ?? null) ? $stored[$user] : [];
    }

    /**
     * Each word of the search (SearchText::words()) must be found — in the chosen column, else in one of the app's
     * searchable columns; every comparison is on the normalized forms (SearchQuery).
     *
     * @param  Builder<Model>  $query
     */
    private function applySearch(Builder $query, string $search): void
    {
        $column = $this->activeSearchColumn();

        foreach (SearchText::words($search) as $word) {
            $query->where(function (Builder $query) use ($column, $word): void {
                if ($column !== null) {
                    $this->searchOneColumn($query, $column, $word);

                    return;
                }

                $isFirst = true;
                foreach ($this->getTable()->getColumns() as $tableColumn) {
                    if (! $tableColumn->isHidden() && $tableColumn->isGloballySearchable()) {
                        $tableColumn->applySearchConstraint($query, $word, $isFirst);
                    }
                }
                if ($isFirst) {
                    $query->whereRaw('1 = 0');
                }
            });
        }
    }

    /** @param  Builder<Model>  $query */
    private function searchOneColumn(Builder $query, string $name, string $word): void
    {
        $column = $this->getTable()->getColumn($name);
        if ($column !== null && $column->isGloballySearchable()) {
            $isFirst = true;
            $column->applySearchConstraint($query, $word, $isFirst);

            return;
        }

        $field = $this->filterFieldMap()[$name] ?? null;
        if ($field === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        $condition = fn (FilterOperator $operator, array $values): FilterCondition => new FilterCondition($field->name, $field->type, $operator, $values);
        $custom = $field->getQuery();
        $relationship = $field->getRelationship();
        $qualified = fn (Builder $query, string $column): string => $query->qualifyColumn($column);

        switch ($field->type) {
            case FilterFieldType::Text:
            case FilterFieldType::Textarea:
                match (true) {
                    $custom !== null => $custom($query, $condition(FilterOperator::Contains, [$word])),
                    $relationship !== null => $query->whereHas($relationship, fn (Builder $q) => SearchQuery::where($q, $qualified($q, (string) $field->getRelatedColumn()), $word)),
                    default => SearchQuery::where($query, $qualified($query, $field->getColumn()), $word),
                };

                return;
            case FilterFieldType::Number:
                $number = str_replace([',', '円', '¥', '\\'], '', $word);
                if (! is_numeric($number)) {
                    $query->whereRaw('1 = 0');
                } elseif ($custom !== null) {
                    $custom($query, $condition(FilterOperator::Equals, [$number]));
                } else {
                    self::castContains($query, $qualified($query, $field->getColumn()), $number);
                }

                return;
            case FilterFieldType::Date:
            case FilterFieldType::DateTime:
                $custom === null
                    ? self::castContains($query, $qualified($query, $field->getColumn()), self::searchDate($word))
                    : $query->whereRaw('1 = 0');

                return;
            case FilterFieldType::Choice:
            case FilterFieldType::User:
                // the options whose label has the word
                $values = array_map('strval', array_keys(array_filter(
                    $field->getOptions(),
                    fn (string $label): bool => array_filter(SearchText::variants($word), fn (string $form): bool => str_contains(SearchText::normalize($label), $form)) !== [],
                )));
                match (true) {
                    $values === [] => $query->whereRaw('1 = 0'),
                    $custom !== null => $custom($query, $condition(FilterOperator::In, $values)),
                    $relationship !== null => $query->whereHas($relationship, fn (Builder $q) => $q->whereIn($qualified($q, (string) $field->getRelatedColumn()), $values)),
                    default => $query->whereIn($qualified($query, $field->getColumn()), $values),
                };

                return;
        }
    }

    /** @param  Builder<Model>  $query */
    private static function castContains(Builder $query, string $column, string $text): void
    {
        $query->whereRaw('CAST('.$query->getQuery()->getGrammar()->wrap($column)." AS TEXT) LIKE ? ESCAPE '!'", ['%'.SearchQuery::escapeLike($text).'%']);
    }

    /** A date as typed (2026/10/5, 2026年10月, 10/05 …) in the stored form: parts joined by -, months and days 2 digits. */
    private static function searchDate(string $word): string
    {
        $parts = preg_split('/[\/.\-年月日]+/u', $word, flags: PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return $word;
        }

        return implode('-', array_map(fn (string $part): string => ctype_digit($part) && strlen($part) === 1 ? '0'.$part : $part, $parts));
    }
}
