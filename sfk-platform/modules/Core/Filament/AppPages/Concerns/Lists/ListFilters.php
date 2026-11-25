<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Lists;

use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Renderless;
use Livewire\Attributes\Session;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Filters\RecordFilterQuery;
use Modules\Core\Application\Views\ListViewInvalid;
use Modules\Core\Application\Views\ListViewService;
use Modules\Core\Domain\Filters\RecordFilter;

/**
 * 絞り込む of an app list (core::filament.app-filter): 適用 filters for the session, 保存 (administrators) stores a
 * new 一覧; the 条件 / ソート of the view otherwise. Fields the user may not see are not offered.
 *
 * Part of Modules\Core\Filament\AppPages\AppListRecords (the shared app list); uses its views, fields and access rights.
 */
trait ListFilters
{
    /** @var array<string, mixed>|null 適用 conditions (RecordFilter::toArray()); null = those of the view */
    #[Session]
    public ?array $appFilter = null;

    /** The 条件 / ソート in effect: applied in this session, else those of the view. */
    public function activeFilter(): RecordFilter
    {
        $schema = $this->listViewSchema();

        return RecordFilter::fromArray($this->appFilter ?? $this->currentView()->filter, $schema->filterTypes(), $schema->sortableFields());
    }

    /**
     * Static data of the 絞り込む dialog (core::filament.app-filter). Renderless: opening the dialog changes nothing on
     * the page — without it, the whole list was drawn again and sent back with the data (~1 MB, as long as a reload of a
     * 100-row list; docs/performance.md).
     *
     * @return array{fields: list<array<string, mixed>>, sortable: list<array{name: string, label: string}>, canSave: bool}
     */
    #[Renderless]
    public function filterDialog(): array
    {
        return [
            ...self::filterEditorConfig($this->allowedFilterFields()),
            'canSave' => $this->canSaveListViews(),
        ];
    }

    /**
     * Fields and ソート choices of a 条件 / ソート editor (the 絞り込む dialog and the 一覧 screen).
     *
     * @param  list<FilterField>  $fields
     * @return array{fields: list<array<string, mixed>>, sortable: list<array{name: string, label: string}>}
     */
    public static function filterEditorConfig(array $fields): array
    {
        return [
            'fields' => array_map(fn (FilterField $field): array => $field->toDialog(), $fields),
            'sortable' => array_values(array_map(
                fn (FilterField $field): array => ['name' => $field->name, 'label' => $field->label],
                array_filter($fields, fn (FilterField $field): bool => $field->isSortable()),
            )),
        ];
    }

    /**
     * Content the dialog opens with: the filter in effect; ソート defaults to the list's default order.
     *
     * @return array<string, mixed>
     */
    public function appFilterState(): array
    {
        $state = $this->activeFilter()->toArray();

        $default = $this->getTable()->getDefaultSortColumn();
        if ($state['sorts'] === [] && $default !== null && in_array($default, $this->listViewSchema()->sortableFields(), true)) {
            $state['sorts'] = [['field' => $default, 'direction' => $this->getTable()->getDefaultSortDirection() === 'desc' ? 'desc' : 'asc']];
        }

        return $state;
    }

    /** 適用: filters the list with the dialog content for this session. */
    public function applyAppFilter(mixed $data): void
    {
        $schema = $this->listViewSchema();
        $this->appFilter = RecordFilter::fromArray($data, $schema->filterTypes(), $schema->sortableFields())->toArray();
        $this->resetPage();
    }

    /** 保存 (administrators): stores the dialog content as a new 一覧 with the current view's layout; returns an error. */
    public function saveAppFilter(mixed $data, mixed $name): ?string
    {
        $app = $this->appResource()::app();

        if (! $this->canSaveListViews() || $app === null) {
            return '一覧を保存する権限がありません。';
        }

        $current = $this->currentView();
        $user = auth()->user();

        try {
            $view = app(ListViewService::class)->save($app, null, [
                'name' => $name,
                'type' => $current->type->value,
                'columns' => $current->isBuiltIn() ? array_keys($this->listColumns()) : $current->columns,
                'dateField' => $current->dateField,
                'titleField' => $current->titleField,
                'filter' => $data,
            ], $this->listViewSchema(), $user instanceof User ? $user->id : null);
        } catch (ListViewInvalid $invalid) {
            return $invalid->errors['name'] ?? (array_values($invalid->errors)[0] ?? null);
        }

        $this->views = null;
        $this->listView = (string) $view->id;
        $this->appFilter = null;
        $this->resetTable();

        Notification::make()->title('一覧を保存しました')->success()->send();

        return null;
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applyFiltersToTableQuery(Builder $query, bool $isResolvingRecord = false): Builder
    {
        $query = parent::applyFiltersToTableQuery($query, $isResolvingRecord);

        if ($isResolvingRecord) {
            return $query;
        }

        $user = auth()->user();

        return RecordFilterQuery::filter($query, $this->activeFilter(), $this->filterFieldMap(), $user instanceof User ? $user->id : null, CarbonImmutable::now());
    }

    /**
     * ソート of 絞り込む / of the view replaces the default order; a click on a column header still wins.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function applySortingToTableQuery(Builder $query): Builder
    {
        $filter = $this->activeFilter();

        if (blank($this->getTableSortColumn()) && $filter->sorts !== []) {
            return RecordFilterQuery::sort($query, $filter, $this->filterFieldMap())
                ->orderBy($query->getModel()->getQualifiedKeyName());
        }

        return parent::applySortingToTableQuery($query);
    }

    /** Managing 一覧 is app management: administrators and アプリ管理 of the app (AppAccess::manages()). */
    protected function canSaveListViews(): bool
    {
        return $this->isAppAdministrator() && $this->filterFields() !== [];
    }

    /** @return array<string, FilterField> */
    private function filterFieldMap(): array
    {
        $fields = [];
        foreach ($this->allowedFilterFields() as $field) {
            $fields[$field->name] = $field;
        }

        return $fields;
    }

    /** @return list<FilterField> the 絞り込む fields the user may see */
    private function allowedFilterFields(): array
    {
        return array_values(array_filter($this->filterFields(), fn (FilterField $field): bool => $this->fieldLevel($field->name)->canView()));
    }
}
