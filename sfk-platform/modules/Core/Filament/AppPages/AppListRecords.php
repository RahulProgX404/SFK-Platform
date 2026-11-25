<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use App\Models\User;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\View as ViewComponent;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Livewire\Attributes\Session;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Application\Filters\FilterField;
use Modules\Core\Application\Records\RelationBatch;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Application\Views\ListViewSchema;
use Modules\Core\Application\Views\ListViewService;
use Modules\Core\Domain\Apps\FieldAccessLevel;
use Modules\Core\Domain\Documents\OutputPlacement;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;
use Modules\Core\Filament\AppPages\Concerns\Lists\BulkDeletion;
use Modules\Core\Filament\AppPages\Concerns\Lists\CalendarView;
use Modules\Core\Filament\AppPages\Concerns\Lists\InlineEditing;
use Modules\Core\Filament\AppPages\Concerns\Lists\LegacyButtons;
use Modules\Core\Filament\AppPages\Concerns\Lists\ListExports;
use Modules\Core\Filament\AppPages\Concerns\Lists\ListFilters;
use Modules\Core\Filament\AppPages\Concerns\Lists\ListPagination;
use Modules\Core\Filament\AppPages\Concerns\Lists\ListSearch;
use Modules\Core\Filament\Support\MenuButtons;
use Modules\Core\Filament\Support\SummaryCard;
use Modules\Core\Filament\Tables\InlineField;

/**
 * Record list of an app (design_ref/app-index.png): the app name with the home link and the description popup,
 * エクスポート (帳票出力) and 新規 at the top right; the app's summary cards (summaryCards()); the 一覧 as tabs (the app's
 * views from アプリの設定 → 一覧, ListViewService, the topmost being the default, （すべて） last); the toolbar — search,
 * 絞り込む (filterFields(), core::filament.app-filter), the switch showing the app's legacy buttons (menuActions(), with
 * ボタンの説明), then 並び替え, 列 (the columns shown) and ⚙ (optionActions(), 一括削除, アプリの設定); under the table
 * 表示件数 and the page numbers. Every database column sorts by a click on its header (昇順 ⇄ 降順).
 * A 表形式 view shows its columns in its order (viewColumns(), read by the app's table), a カレンダー形式 view a month
 * calendar of the records by its 日付 field (core::filament.app-calendar), a カスタマイズ形式 view the app's screen of
 * customListViews() over the records of the view (core::filament.app-custom-view; e.g. a Gantt chart).
 *
 * An app supplies listColumns() (every column, the order of （すべて）), defaultListViews() (stored the first time) and
 * filterFields(). 絞り込む: 適用 filters for the session (dropped when the view changes), 保存 (administrators) stores
 * a new 一覧 with the current view's columns. 表示件数 is each user's choice and applies to every app.
 *
 * その他の設定: インライン編集 — the rows end with the pencil and × (Tables\AppRecordActions); the pencil turns the row's
 * cells of inlineFields() into inputs (Tables\AppTextColumn) with 保存 / キャンセル, saved like the edit screen (rules
 * of the fields, 変更履歴). 一括削除 — 「…」 → 一括削除 (administrators with 削除) deletes every record of the list
 * as filtered and searched.
 *
 * The parts live in Concerns\Lists: InlineEditing, ListSearch, ListFilters, ListPagination, CalendarView, ListExports,
 * LegacyButtons and BulkDeletion; this class keeps the views, the table and what an app overrides.
 */
abstract class AppListRecords extends ListRecords
{
    use BulkDeletion;
    use CalendarView;
    use HasAppChrome;
    use InlineEditing;
    use LegacyButtons;
    use ListExports;
    use ListFilters;
    use ListPagination;
    use ListSearch;

    #[Url(as: 'view')]
    public string $listView = '';

    /** ?new=1: the app's quick-create panel opens with the list (AppResource::newRecordUrl) */
    #[Url(as: 'new')]
    public ?string $newRecord = null;

    /** ?duplicate=<id> with ?new=1: レコードを再利用する in the quick-create panel */
    #[Url(as: 'duplicate')]
    public ?string $duplicateRecord = null;

    /** ?created=<id>: the record just added in the quick-create panel, marked in the list */
    #[Url(as: 'created')]
    public ?string $createdRecord = null;

    /** @var list<ListView>|null */
    private ?array $views = null;

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
     * Speed (pass 2026-11-24): belongs-to relations of the rows (or dotted paths, "order.customer") that point at the same
     * table several times — e.g. 受注's 得意先 / 製版先 / 製造先 … all in 取引先, or the users of 申請管理. The table's eager
     * loads of them are taken off and they are loaded with ONE query per related table for the page of rows
     * (RelationBatch) — each eager load was a round trip of its own on every click (page, sort, search).
     *
     * @return list<string>
     */
    protected function batchedTableRelations(): array
    {
        return [];
    }

    /**
     * The page of rows, with batchedTableRelations() loaded per related table.
     *
     * @param  Builder<Model>  $query
     * @return Paginator<int, Model>|CursorPaginator<int, Model>
     */
    protected function paginateTableQuery(Builder $query): Paginator|CursorPaginator
    {
        $paths = $this->batchedTableRelations();
        if ($paths === []) {
            return parent::paginateTableQuery($query);
        }

        $records = parent::paginateTableQuery($query->without($paths));
        RelationBatch::loadMany(array_filter($records->items(), fn (mixed $record): bool => $record instanceof Model), $paths, loadRest: true);

        return $records;
    }

    /**
     * The app's カスタマイズ形式 screens: key (stored on the view, ListView::custom()) => Blade view, drawn with `$page`
     * (this page; e.g. getFilteredTableQuery() gives the records of the view).
     *
     * @return array<string, string>
     */
    public function customListViews(): array
    {
        return [];
    }

    /** The Blade view of the current カスタマイズ形式 view (null when the app no longer has it). */
    public function customViewTemplate(): ?string
    {
        $view = $this->currentView();

        return $view->isCustom() ? ($this->customListViews()[(string) $view->customView] ?? null) : null;
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
        // an app of one screen shows its first view only (AppResource::isSingleScreen)
        if ($this->appResource()::isSingleScreen()) {
            return $this->listViews()[0];
        }

        foreach ($this->listViews() as $view) {
            if ($view->key === $this->listView) {
                return $view;
            }
        }

        return $this->listViews()[0];
    }

    /** @return list<string> columns of the current 表形式 view, in its order (without the fields the user may not see) */
    public function viewColumns(): array
    {
        $columns = array_values(array_filter(
            $this->currentView()->columns,
            fn (string $column): bool => array_key_exists($column, $this->listColumns()),
        ));
        $columns = $columns !== [] ? $columns : array_keys($this->listColumns());

        return array_values(array_filter($columns, fn (string $column): bool => $this->fieldLevel($column)->canView()));
    }

    public function mount(): void
    {
        parent::mount();

        if (! array_key_exists($this->listView, $this->allListViews())) {
            $this->listView = $this->listViews()[0]->key;
        }

        $this->searchColumn = self::searchColumns()[static::class] ?? null;
    }

    public function updatedListView(): void
    {
        $this->appFilter = null;
        $this->cancelInlineEdit();
        // the table of the view now shown, with its 列 (each view keeps its own choice)
        $this->bootedInteractsWithTable();
        $this->cachedDefaultTableColumnState = null;
        $this->setTableColumns($this->loadTableColumnsFromSession());
        $this->resetTable();
    }

    /** 列: the columns hidden by the user are kept per 一覧 (views have different columns). */
    public function getTableColumnsSessionKey(): string
    {
        return parent::getTableColumnsSessionKey().'_'.md5($this->listView);
    }

    public function appPageKind(): string
    {
        return 'list';
    }

    public function getTitle(): string
    {
        return $this->appResource()::displayName();
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

        // check boxes for 帳票出力 of the checked rows and for 選択したレコードを削除
        if ($this->documentOutputsAt(OutputPlacement::ListChecked) !== [] || $this->appResource()::canDeleteAny()) {
            $table->selectable();
        }

        return $table
            ->paginated([$this->pageSize()])
            ->defaultPaginationPageOption($this->pageSize())
            // 検索: the app's searchable columns, or the one chosen with the icon right of the box (searchColumn)
            ->searchable($this->searchColumnOptions($table) !== [])
            ->searchUsing(fn (Builder $query, string $search) => $this->applySearch($query, $search))
            ->searchPlaceholder(fn (): string => ($label = $this->searchColumnOptions()[$this->activeSearchColumn() ?? ''] ?? null) !== null ? $label.'で検索' : '検索')
            ->searchDebounce('300ms')
            // no 列 chooser: the columns of a list are set by its 一覧 (アプリの設定 → 一覧)
            ->columnManager(false)
            ->recordClasses(fn (Model $record): ?string => $this->createdRecord !== null && (string) $record->getKey() === $this->createdRecord ? 'sfk-row-created' : null);
    }

    /**
     * The app's quick-create panel under the list (AppResource::quickCreateComponent), opened by 新規 and ?new=1; null when
     * the app has a create page or the user may not add records.
     *
     * @return array{component: class-string, params: array{app: string, open: bool, duplicate: string|null}}|null
     */
    public function quickCreate(): ?array
    {
        $resource = $this->appResource();
        $component = $resource::quickCreateComponent();
        if ($component === null || $resource::hasPage('create') || ! $resource::canCreate()) {
            return null;
        }

        return ['component' => $component, 'params' => ['app' => $resource::appCode(), 'open' => filled($this->newRecord), 'duplicate' => $this->duplicateRecord]];
    }

    /**
     * 並び替え (right of the search row): the columns of the list that sort, name => label.
     *
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        $options = [];
        foreach ($this->getTable()->getVisibleColumns() as $name => $column) {
            if ($column->isSortable() && filled($column->getLabel())) {
                $options[(string) $name] = (string) $column->getLabel();
            }
        }

        return $options;
    }

    /**
     * The summary of the listed records (filtered and searched) shown as cards above the 一覧 tabs; none by default.
     * tone: blue | green | amber | red | purple | slate; icon: a Heroicon name.
     *
     * @return list<array{label: string, value: string, tone?: string, icon?: string}>
     */
    public function summaryCards(): array
    {
        return [];
    }

    /**
     * The summary cards drawn above the 一覧 tabs: only for users who see everyone's money — 財務分析を表示 with 財務分析の
     * 範囲 全体 (User::seesOverallFinance(); super admins always) — as the totals of the apps are the company's money
     * (user, 2026-11-11). Others get none, and nothing is added up for them.
     *
     * @return list<array{label: string, value: string, tone?: string, icon?: string}>
     */
    public function shownSummaryCards(): array
    {
        $user = auth()->user();

        return $user instanceof User && $user->seesOverallFinance() ? $this->summaryCards() : [];
    }

    /**
     * A card of summaryCards() (Support\SummaryCard::make()).
     *
     * @return array{label: string, value: string, tone: string, icon: string}
     */
    protected static function summaryCard(string $label, int|float|string|null $value, string $kind = 'yen', ?string $tone = null, ?string $icon = null): array
    {
        return SummaryCard::make($label, $value, $kind, $tone, $icon);
    }

    /** A click on the sorted column's header switches 昇順 ⇄ 降順 (it does not go back to the default order). */
    public function sortTable(?string $column = null, ?string $direction = null): void
    {
        if ($direction === null && $column !== null && $column === $this->getTableSortColumn()) {
            $direction = $this->getTableSortDirection() === 'asc' ? 'desc' : 'asc';
        }

        parent::sortTable($column, $direction);
    }

    /** 並び替え → 標準: back to the order of the view / the app (no column sort). */
    public function useDefaultSort(): void
    {
        $this->tableSort = null;
        $this->updatedTableSort();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
            ViewComponent::make('core::filament.app-calendar')->visible(fn (): bool => $this->currentView()->isCalendar()),
            ViewComponent::make('core::filament.app-custom-view')->visible(fn (): bool => $this->currentView()->isCustom()),
            EmbeddedTable::make()->visible(fn (): bool => $this->currentView()->isTable()),
            // horizontal scroll at the bottom of the screen while the end of the table is out of view
            ViewComponent::make('core::filament.app-list-scrollbar')->visible(fn (): bool => $this->currentView()->isTable()),
            RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
        ]);
    }

    /**
     * A part of the table toolbar (render hooks of every panel, BasePanelProvider; empty outside app lists,
     * core::filament.app-list-tools): 'start' — right of the search box: the search column, 絞り込む and the switch of
     * the legacy buttons; 'sort' — 並び替え before ⚙; 'settings' — ⚙; 'menu' — the legacy buttons under the
     * toolbar.
     */
    public static function toolbarPart(string $part): string
    {
        $page = Livewire::current();

        return $page instanceof self ? view('core::filament.app-list-tools', ['page' => $page, 'part' => $part])->render() : '';
    }

    /**
     * The header actions by place: the legacy buttons (menu), エクスポート (export) and ⚙ (settings).
     *
     * @return array{menu: list<Action|ActionGroup>, export: Action|ActionGroup|null, settings: Action|ActionGroup|null}
     */
    public function toolbarActions(): array
    {
        $actions = array_values($this->getCachedHeaderActions());
        $first = fn (Closure $is): Action|ActionGroup|null => array_values(array_filter($actions, fn ($action): bool => $is($action) && $action->isVisible()))[0] ?? null;

        return [
            'menu' => array_values(array_filter($actions, fn ($action): bool => MenuButtons::isMenuButton($action) && $action->isVisible())),
            'export' => $first(MenuButtons::isExport(...)),
            'settings' => $first(MenuButtons::isSettings(...)),
        ];
    }

    public function getFooter(): View
    {
        return view('core::filament.app-list-footer', ['page' => $this]);
    }

    /**
     * The app's legacy buttons, エクスポート (帳票出力) and ⚙: the app's option actions (e.g. ファイルに書き出す), 一括削除
     * and the shortcuts of アプリの設定 (administrators).
     *
     * @return array<Action|ActionGroup>
     */
    protected function getHeaderActions(): array
    {
        return array_values(array_filter([
            ...array_map(MenuButtons::mark(...), $this->menuActions()),
            MenuButtons::export($this->exportActions()),
            MenuButtons::settings([
                ...$this->optionActions(),
                $this->exportCsvAction(),
                $this->bulkDeleteAction(),
                ...$this->appSettingsActions(),
            ])
                // wide enough for 「選択したレコードを削除」 on one line
                ->dropdownWidth(Width::ExtraSmall),
        ]));
    }

    /**
     * Entries of the ⚙ menu above 一括削除.
     *
     * @return list<Action>
     */
    protected function optionActions(): array
    {
        return [];
    }

    /**
     * ⚙ → the shortcuts of アプリの設定 (administrators, HasAppChrome::appSettingsMenu()) under a divider.
     *
     * @return list<ActionGroup>
     */
    private function appSettingsActions(): array
    {
        $links = [];
        foreach ($this->appChrome()['settings_menu'] as $index => $item) {
            $links[] = Action::make('appSetting'.$index)->label($item['label'])->icon(Heroicon::OutlinedAdjustmentsHorizontal)->url($item['url']);
        }

        return $links === [] ? [] : [ActionGroup::make($links)->dropdown(false)];
    }

    /** @return list<string> */
    private static function databaseColumns(?Model $model): array
    {
        static $columns = [];

        if ($model === null) {
            return [];
        }

        $table = $model->getTable();

        // from the cache between requests (each schema lookup is a round trip; migrations bump it)
        return $columns[$table] ??= ReferenceCache::remember('columns:'.$table, fn (): array => DatabaseSchema::getColumnListing($table));
    }

    /**
     * フィールドのアクセス権 of the signed-in user in this app (FieldAccess; fields not listed: 編集).
     *
     * @return array<string, FieldAccessLevel>
     */
    private function fieldLevels(): array
    {
        $user = auth()->user();
        $app = $this->appResource()::app();

        return $user instanceof User && $app !== null ? FieldAccess::levels($user, $app) : [];
    }

    private function fieldLevel(string $name): FieldAccessLevel
    {
        return $this->fieldLevels()[$name] ?? FieldAccessLevel::Edit;
    }

    /** @return list<string> fields the user may not see (フィールドのアクセス権 なし), e.g. for ファイルに書き出す */
    protected function hiddenFields(): array
    {
        return array_keys(array_filter($this->fieldLevels(), fn (FieldAccessLevel $level): bool => ! $level->canView()));
    }
}
