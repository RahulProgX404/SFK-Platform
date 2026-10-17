<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Filters\RecordFilterQuery;
use Modules\Core\Application\History\HasRecordHistory;
use Modules\Core\Application\History\RecordHistory;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Application\Views\ListViewInvalid;
use Modules\Core\Application\Views\ListViewSchema;
use Modules\Core\Application\Views\ListViewService;
use Modules\Core\Domain\Filters\FilterFieldType;
use Modules\Core\Domain\Filters\RecordFilter;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;
use Modules\Core\Filament\Tables\InlineField;

/**
 * Record list of an app, in the legacy layout: app frame; the 一覧 dropdown (the app's views from アプリの設定 → 一覧,
 * ListViewService, the topmost being the default, （すべて） last); the 絞り込む funnel (filterFields(),
 * core::filament.app-filter); ＋ / gear (アプリの設定) / 「…」 (optionActions() and 表示件数); 「1 - 100（N件中）」 paging.
 * Every database column sorts by a click on its header (昇順 ⇄ 降順); the search box sits on the left.
 * A 表形式 view shows its columns in its order (viewColumns(), read by the app's table), a カレンダー形式 view a month
 * calendar of the records by its 日付 field (core::filament.app-calendar).
 *
 * An app supplies listColumns() (every column, the order of （すべて）), defaultListViews() (stored the first time) and
 * filterFields(). 絞り込む: 適用 filters for the session (dropped when the view changes), 保存 (administrators) stores
 * a new 一覧 with the current view's columns. 表示件数 is each user's choice and applies to every app.
 *
 * その他の設定: インライン編集 — the rows end with the pencil and × (Tables\AppRecordActions); the pencil turns the row's
 * cells of inlineFields() into inputs (Tables\AppTextColumn) with 保存 / キャンセル, saved like the edit screen (rules
 * of the fields, 変更履歴). 一括削除 — 「…」 → 一括削除 (administrators with 削除) deletes every record of the list
 * as filtered and searched.
 */
abstract class AppListRecords extends ListRecords
{
    use HasAppChrome;

    /** 表示件数 choices; blank = DEFAULT_PAGE_SIZE */
    public const PAGE_SIZES = [20, 40, 60, 80, 100, 200, 300];

    public const DEFAULT_PAGE_SIZE = 100;

    /** Most records drawn on one calendar month. */
    public const CALENDAR_LIMIT = 1000;

    #[Url(as: 'view')]
    public string $listView = '';

    /** カレンダー形式: the month shown (Y-m); blank = this month */
    #[Url(as: 'month')]
    public string $calendarMonth = '';

    /** @var array<string, mixed>|null 適用 conditions (RecordFilter::toArray()); null = those of the view */
    #[Session]
    public ?array $appFilter = null;

    /** インライン編集: key of the row being edited */
    public ?string $inlineRecordKey = null;

    /** @var array<string, mixed> インライン編集: values of the row being edited, by field */
    public array $inlineData = [];

    /** @var list<ListView>|null */
    private ?array $views = null;

    private ?bool $rowActionsEnabled = null;

    /**
     * Every column the app's list can show (name => label), in the order of （すべて）.
     *
     * @return array<string, string>
     */
    public function listColumns(): array
    {
        return [];
    }

    /**
     * The app's code-defined views (e.g. from SYSTEM_BRAIN views.md), stored as its 一覧 the first time.
     *
     * @return list<ListView>
     */
    public function defaultListViews(): array
    {
        return [];
    }

    /**
     * Fields offered in 絞り込む and in the 絞り込み / ソート of a 一覧, in form order; none = no funnel.
     *
     * @return list<FilterField>
     */
    public function filterFields(): array
    {
        return [];
    }

    /**
     * Fields インライン編集 can change, with the rules of the app's form; none = the pencil opens the edit screen.
     *
     * @return list<InlineField>
     */
    public function inlineFields(): array
    {
        return [];
    }

    public function inlineField(string $name): ?InlineField
    {
        foreach ($this->inlineFields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }

    /** その他の設定 → インライン編集: the pencil and × at the end of the rows. */
    public function rowActionsEnabled(): bool
    {
        return $this->rowActionsEnabled ??= $this->appSetting('inline_edit_enabled');
    }

    public function isInlineEditing(Model $record): bool
    {
        return $this->inlineRecordKey !== null && $this->inlineRecordKey === (string) $record->getKey();
    }

    /** The pencil of a row: its cells become inputs. */
    public function startInlineEdit(Model|string $record): void
    {
        $record = $this->inlineRecord($record instanceof Model ? (string) $record->getKey() : $record);
        $this->resetErrorBag();

        if ($record === null) {
            $this->cancelInlineEdit();

            return;
        }

        $this->inlineRecordKey = (string) $record->getKey();
        $this->inlineData = $this->inlineEditState($record);
    }

    /** 保存 of the row being edited: checked with the fields' rules, saved and kept in the 変更履歴. */
    public function saveInlineEdit(): void
    {
        $record = $this->inlineRecord($this->inlineRecordKey);
        if ($record === null) {
            $this->cancelInlineEdit();

            return;
        }

        $rules = [];
        $attributes = [];
        foreach ($this->inlineFields() as $field) {
            foreach ($field->rules($record) as $path => $fieldRules) {
                $rules['inlineData.'.$path] = $fieldRules;
            }
            $attributes['inlineData.'.$field->name] = $this->listColumns()[$field->name] ?? $field->name;
            $attributes['inlineData.'.$field->name.'.*'] = $attributes['inlineData.'.$field->name];
        }

        /** @var array<string, mixed> $data */
        $data = $this->validate($rules, [], $attributes)['inlineData'] ?? [];
        foreach ($this->inlineFields() as $field) {
            $data[$field->name] = match (true) {
                $field->isBoolean() => (bool) ($data[$field->name] ?? false),
                $field->isMultiple() => array_values((array) ($data[$field->name] ?? [])),
                default => blank($data[$field->name] ?? null) ? null : $data[$field->name],
            };
        }

        DB::transaction(function () use ($record, $data): void {
            $this->saveInlineRecord($record, $data);

            if ($record instanceof HasRecordHistory && $this->appSetting('record_history_enabled')) {
                $user = auth()->user();
                app(RecordHistory::class)->record($record, $user instanceof User ? $user->id : null);
            }
        });

        $this->cancelInlineEdit();
    }

    public function cancelInlineEdit(): void
    {
        $this->inlineRecordKey = null;
        $this->inlineData = [];
        $this->resetErrorBag();
    }

    public function listViewSchema(): ListViewSchema
    {
        return new ListViewSchema($this->listColumns(), $this->filterFields());
    }

    /** @return list<ListView> the dropdown, （すべて） last */
    public function listViews(): array
    {
        return $this->views ??= app(ListViewService::class)->views($this->appResource()::app(), $this->defaultListViews(), $this->listViewSchema());
    }

    /** @return array<string, string> key => 一覧名 */
    public function allListViews(): array
    {
        $views = [];
        foreach ($this->listViews() as $view) {
            $views[$view->key] = $view->name;
        }

        return $views;
    }

    public function currentView(): ListView
    {
        foreach ($this->listViews() as $view) {
            if ($view->key === $this->listView) {
                return $view;
            }
        }

        return $this->listViews()[0];
    }

    /** @return list<string> columns of the current 表形式 view, in its order */
    public function viewColumns(): array
    {
        $columns = array_values(array_filter(
            $this->currentView()->columns,
            fn (string $column): bool => array_key_exists($column, $this->listColumns()),
        ));

        return $columns !== [] ? $columns : array_keys($this->listColumns());
    }

    public function mount(): void
    {
        parent::mount();

        if (! array_key_exists($this->listView, $this->allListViews())) {
            $this->listView = $this->listViews()[0]->key;
        }
    }

    public function updatedListView(): void
    {
        $this->appFilter = null;
        $this->cancelInlineEdit();
        $this->resetTable();
    }

    public function appPageKind(): string
    {
        return 'list';
    }

    public function getTitle(): string
    {
        return $this->appResource()::appName();
    }

    /** Every column of a database field sorts by a click on its header; columns of other data define their own sort. */
    public function table(Table $table): Table
    {
        $fields = self::databaseColumns($table->getQuery()?->getModel());

        foreach ($table->getColumns() as $column) {
            if (! $column->isSortable() && in_array($column->getName(), $fields, true)) {
                $column->sortable();
            }
        }

        return $table
            ->paginated([$this->pageSize()])
            ->defaultPaginationPageOption($this->pageSize())
            ->striped();
    }

    /** A click on the sorted column's header switches 昇順 ⇄ 降順 (it does not go back to the default order). */
    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        if ($direction === null && $column !== null && $column === $this->getTableSortColumn()) {
            $direction = $this->getTableSortDirection() === 'asc' ? 'desc' : 'asc';
        }

        parent::sortTable($column, $direction);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            ViewComponent::make('core::filament.app-calendar')->visible(fn (): bool => $this->currentView()->isCalendar()),
            EmbeddedTable::make()->visible(fn (): bool => ! $this->currentView()->isCalendar()),
            // horizontal scroll at the bottom of the screen while the end of the table is out of view
            ViewComponent::make('core::filament.app-list-scrollbar')->visible(fn (): bool => ! $this->currentView()->isCalendar()),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    /** 表示件数 of the signed-in user. */
    public function pageSize(): int
    {
        $user = auth()->user();
        $size = $user instanceof User ? (int) $user->list_page_size : 0;

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::DEFAULT_PAGE_SIZE;
    }

    public function setPageSize(int $size): void
    {
        $user = auth()->user();
        if (! $user instanceof User || ! in_array($size, self::PAGE_SIZES, true)) {
            return;
        }

        $user->forceFill(['list_page_size' => $size])->save();
        $this->tableRecordsPerPage = $size;
        $this->resetTable();
    }

    /** The 条件 / ソート in effect: applied in this session, else those of the view. */
    public function activeFilter(): RecordFilter
    {
        $schema = $this->listViewSchema();

        return RecordFilter::fromArray($this->appFilter ?? $this->currentView()->filter, $schema->filterTypes(), $schema->sortableFields());
    }

    public function isFiltered(): bool
    {
        return $this->activeFilter()->hasConditions();
    }

    /**
     * Static data of the 絞り込む dialog (core::filament.app-filter).
     *
     * @return array{fields: list<array<string, mixed>>, sortable: list<array{name: string, label: string}>, canSave: bool}
     */
    public function filterDialog(): array
    {
        return [
            ...self::filterEditorConfig($this->filterFields()),
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

    /** @return array{from: int, to: int, total: int, previous: bool, next: bool}|null */
    public function appPager(): ?array
    {
        if ($this->currentView()->isCalendar()) {
            return null;
        }

        $records = $this->getTableRecords();

        if (! $records instanceof LengthAwarePaginator || $records->total() === 0) {
            return null;
        }

        return [
            'from' => (int) $records->firstItem(),
            'to' => (int) $records->lastItem(),
            'total' => $records->total(),
            'previous' => $records->currentPage() > 1,
            'next' => $records->hasMorePages(),
        ];
    }

    public function previousAppPage(): void
    {
        $this->previousPage($this->getTablePaginationPageName());
    }

    public function nextAppPage(): void
    {
        $this->nextPage($this->getTablePaginationPageName());
    }

    /**
     * 「1 - 100（N件中）」 at the right end of the table toolbar, in the row of the search box (render hook of every
     * panel, BasePanelProvider); empty outside app lists and for calendar views.
     */
    public static function toolbarPager(): string
    {
        $page = Livewire::current();

        return $page instanceof self ? view('core::filament.app-pager', ['page' => $page, 'position' => 'top'])->render() : '';
    }

    public function getFooter(): View
    {
        return view('core::filament.app-pager', ['page' => $this, 'position' => 'bottom']);
    }

    public function calendarMonthStart(): CarbonImmutable
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $this->calendarMonth, $m) === 1) {
            return CarbonImmutable::create((int) $m[1], (int) $m[2], 1) ?? CarbonImmutable::now()->startOfMonth();
        }

        return CarbonImmutable::now()->startOfMonth();
    }

    public function shiftCalendarMonth(int $months): void
    {
        $this->calendarMonth = $this->calendarMonthStart()->addMonthsNoOverflow(max(-1200, min(1200, $months)))->format('Y-m');
    }

    public function showCurrentCalendarMonth(): void
    {
        $this->calendarMonth = '';
    }

    /**
     * カレンダー形式: six weeks (Sunday first) around the shown month with the records of each day (filtered like the
     * list), each with its タイトル and the link to the record.
     *
     * @return list<list<array{date: CarbonImmutable, inMonth: bool, isToday: bool, records: list<array{title: string, url: string|null}>}>>
     */
    public function calendarWeeks(): array
    {
        $view = $this->currentView();
        $fields = $this->filterFieldMap();
        $dateField = $fields[(string) $view->dateField] ?? null;
        $titleField = $fields[(string) $view->titleField] ?? null;

        $first = $this->calendarMonthStart();
        $start = $first->subDays($first->dayOfWeek);
        $end = $start->addWeeks(6);

        $byDay = [];
        if ($dateField !== null) {
            $query = $this->getFilteredSortedTableQuery();
            if ($query !== null) {
                $column = $query->qualifyColumn($dateField->getColumn());
                $format = $dateField->type === FilterFieldType::Date ? 'Y-m-d' : 'Y-m-d H:i:s';
                $records = $query->where($column, '>=', $start->format($format))->where($column, '<', $end->format($format))
                    ->limit(self::CALENDAR_LIMIT)->get();

                foreach ($records as $record) {
                    $date = $record->getAttribute($dateField->getColumn());
                    if ($date === null) {
                        continue;
                    }
                    $title = $titleField !== null ? $record->getAttribute($titleField->getColumn()) : $record->getKey();
                    $byDay[CarbonImmutable::parse($date)->format('Y-m-d')][] = [
                        'title' => $title instanceof BackedEnum ? (string) $title->value : (string) $title,
                        'url' => $this->appResource()::hasPage('view') ? $this->appResource()::getUrl('view', ['record' => $record]) : null,
                    ];
                }
            }
        }

        $weeks = [];
        for ($week = 0; $week < 6; $week++) {
            $days = [];
            for ($weekday = 0; $weekday < 7; $weekday++) {
                $date = $start->addDays($week * 7 + $weekday);
                $days[] = [
                    'date' => $date,
                    'inMonth' => $date->month === $first->month,
                    'isToday' => $date->isToday(),
                    'records' => $byDay[$date->format('Y-m-d')] ?? [],
                ];
            }
            $weeks[] = $days;
        }

        return $weeks;
    }

    /**
     * 「…」: the app's option actions (e.g. ファイルに書き出す) and 表示件数.
     *
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                ...$this->optionActions(),
                Action::make('bulkDelete')
                    ->label('一括削除')
                    ->icon(Heroicon::OutlinedTrash)
                    ->visible(fn (): bool => $this->canBulkDelete())
                    ->modalIcon(Heroicon::OutlinedTrash)
                    ->modalIconColor('danger')
                    ->modalWidth('md')
                    ->modalHeading(fn (): string => ($this->getFilteredTableQuery()?->count() ?? 0).'件のレコードを削除しますか？')
                    ->modalSubmitAction(fn (Action $action): Action => $action->label('削除する')->color('danger'))
                    ->modalCancelActionLabel('キャンセル')
                    ->action(fn () => $this->bulkDelete()),
                Action::make('pageSize')
                    ->label('表示件数')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->hidden(fn (): bool => $this->currentView()->isCalendar())
                    ->modalHeading('表示件数')
                    ->modalWidth('md')
                    ->modalSubmitActionLabel('保存')
                    ->modalCancelActionLabel('キャンセル')
                    ->fillForm(fn (): array => ['size' => (string) $this->pageSize()])
                    ->schema([
                        Radio::make('size')
                            ->hiddenLabel()
                            ->options(array_combine(
                                array_map('strval', self::PAGE_SIZES),
                                array_map(fn (int $size): string => $size.'件', self::PAGE_SIZES),
                            ))
                            ->required(),
                    ])
                    ->action(fn (array $data) => $this->setPageSize((int) $data['size'])),
            ])
                ->label('その他の操作')
                ->icon(Heroicon::EllipsisHorizontal)
                ->color('gray')
                ->iconButton(),
        ];
    }

    /**
     * Entries of the 「…」 menu above 一括削除 and 表示件数.
     *
     * @return list<Action>
     */
    protected function optionActions(): array
    {
        return [];
    }

    /** 一括削除: on in その他の設定, for administrators who may delete the app's records. */
    public function canBulkDelete(): bool
    {
        return $this->appSetting('bulk_delete_enabled') && $this->isAppAdministrator() && $this->appResource()::canDeleteAny();
    }

    /** 一括削除: deletes every record of the list as filtered and searched (one by one, so related data goes too). */
    public function bulkDelete(): void
    {
        $query = $this->canBulkDelete() ? $this->getFilteredTableQuery() : null;
        if ($query === null) {
            return;
        }

        $model = $query->getModel();
        $keys = $query->pluck($model->getQualifiedKeyName())->all();
        $deleted = 0;

        DB::transaction(function () use ($model, $keys, &$deleted): void {
            foreach (array_chunk($keys, 200) as $chunk) {
                foreach ($model->newQuery()->whereKey($chunk)->get() as $record) {
                    if ($this->appResource()::canDelete($record)) {
                        $record->delete();
                        $deleted++;
                    }
                }
            }
        });

        $this->cancelInlineEdit();
        $this->resetPage($this->getTablePaginationPageName());

        Notification::make()->title($deleted.'件のレコードを削除しました')->success()->send();
    }

    /**
     * Values the inputs of インライン編集 start with; add the record's related data here (e.g. selected users).
     *
     * @return array<string, mixed>
     */
    protected function inlineEditState(Model $record): array
    {
        $state = [];
        $columns = $record->getAttributes();
        foreach ($this->inlineFields() as $field) {
            // columns only: related data (e.g. selected users) is added by the app
            $value = array_key_exists($field->name, $columns) ? $record->getAttribute($field->name) : null;
            $state[$field->name] = match (true) {
                $value instanceof BackedEnum => (string) $value->value,
                $field->isBoolean() => (bool) $value,
                $field->isMultiple() => array_values((array) $value),
                default => $value,
            };
        }

        return $state;
    }

    /**
     * Saves the checked values of インライン編集 (inside a transaction); override for related data.
     *
     * @param  array<string, mixed>  $data
     */
    protected function saveInlineRecord(Model $record, array $data): void
    {
        $record->fill($data)->save();
    }

    /** The record behind a row key, when インライン編集 is on, the app has inline fields and the user may edit it. */
    private function inlineRecord(?string $key): ?Model
    {
        if ($key === null || ! $this->rowActionsEnabled() || $this->inlineFields() === []) {
            return null;
        }

        $resource = $this->appResource();
        $record = $resource::getEloquentQuery()->whereKey($key)->first();

        return $record !== null && $resource::canEdit($record) ? $record : null;
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

    /** Managing 一覧 is app management, which is for administrators (Modules\Core\Domain\Apps\AppAbility). */
    protected function canSaveListViews(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator() && $this->filterFields() !== [];
    }

    /** @return list<string> */
    private static function databaseColumns(?Model $model): array
    {
        static $columns = [];

        if ($model === null) {
            return [];
        }

        return $columns[$model->getTable()] ??= DatabaseSchema::getColumnListing($model->getTable());
    }

    /** @return array<string, FilterField> */
    private function filterFieldMap(): array
    {
        $fields = [];
        foreach ($this->filterFields() as $field) {
            $fields[$field->name] = $field;
        }

        return $fields;
    }
}
