<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;
use LogicException;
use Modules\Core\Application\Views\ListView;
use Modules\Core\Application\Views\ListViewInvalid;
use Modules\Core\Application\Views\ListViewSchema;
use Modules\Core\Application\Views\ListViewService;
use Modules\Core\Domain\Views\ListViewType;
use Modules\Core\Filament\AppPages\Concerns\HasAppChrome;
use Modules\Core\Filament\AppResource;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\BusinessApp;

/**
 * アプリの設定 → 一覧 of an app (legacy view settings, administrators only; register it as the resource's `views`
 * page, the gear of the list opens it): 作成済みの一覧 with ドラッグして並び替え (the topmost view is the default),
 * 編集 / 複製 / 削除 and 一覧を追加する; （すべて） stays last and cannot be changed. The 一覧 screen sets 一覧名,
 * レコード一覧の表示形式 (表形式 with its columns, カレンダー形式 with 日付 / タイトル), 絞り込み and ソート.
 * Columns and fields come from the app's list page (AppListRecords). Changes apply immediately.
 */
abstract class AppListViewSettings extends Page
{
    use HasAppChrome;

    protected string $view = 'core::filament.app-list-views';

    /** "new" = 一覧を追加する, an id = 編集; null = 作成済みの一覧 */
    #[Url(as: 'edit')]
    public ?string $editing = null;

    public static function canAccess(array $parameters = []): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator() && static::appModel() !== null;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if ($this->editing !== null && $this->editing !== 'new' && $this->editedView() === null) {
            $this->editing = null;
        }
    }

    public function appPageKind(): string
    {
        return 'settings';
    }

    /** Tab of アプリの設定 shown by this page. */
    public function settingsTab(): string
    {
        return 'views';
    }

    public function getTitle(): string
    {
        return 'アプリの設定';
    }

    /** @return list<AppListView> */
    public function storedViews(): array
    {
        return app(ListViewService::class)->stored($this->app(), $this->listPage()->defaultListViews());
    }

    public function schema(): ListViewSchema
    {
        return $this->listPage()->listViewSchema();
    }

    public function editedView(): ?AppListView
    {
        if ($this->editing === null || $this->editing === 'new' || preg_match('/^\d+$/', $this->editing) !== 1) {
            return null;
        }

        return $this->app()->listViews()->whereKey((int) $this->editing)->first();
    }

    /**
     * Data of the 一覧 screen (sfkViewEditor).
     *
     * @return array<string, mixed>
     */
    public function editorConfig(): array
    {
        $schema = $this->schema();
        $view = $this->editedView();
        $state = $view !== null
            ? ListView::fromModel($view)
            : new ListView('new', '', ListViewType::Table, [], ['match' => 'and', 'conditions' => [], 'sorts' => []]);

        return [
            ...AppListRecords::filterEditorConfig($schema->filterFields),
            'columns' => array_map(fn (string $name, string $label): array => ['name' => $name, 'label' => $label], array_keys($schema->columns), $schema->columns),
            'dateFields' => array_map(fn (string $name, string $label): array => ['name' => $name, 'label' => $label], array_keys($schema->dateFields()), $schema->dateFields()),
            'titleFields' => array_map(fn (string $name, string $label): array => ['name' => $name, 'label' => $label], array_keys($schema->titleFields()), $schema->titleFields()),
            'view' => $state->toArray(),
        ];
    }

    public function addView(): void
    {
        $this->editing = 'new';
    }

    public function editView(int $id): void
    {
        $this->editing = (string) $id;
    }

    public function cancelEditing(): void
    {
        $this->editing = null;
    }

    /**
     * 保存 of the 一覧 screen; returns the errors by input, or null when saved.
     *
     * @return array<string, string>|null
     */
    public function saveView(mixed $input): ?array
    {
        abort_unless(static::canAccess(), 403);
        $user = auth()->user();

        try {
            app(ListViewService::class)->save(
                $this->app(),
                $this->editedView(),
                is_array($input) ? $input : [],
                $this->schema(),
                $user instanceof User ? $user->id : null,
            );
        } catch (ListViewInvalid $invalid) {
            return $invalid->errors;
        }

        $this->editing = null;
        Notification::make()->title('一覧を保存しました')->success()->send();

        return null;
    }

    public function copyView(int $id): void
    {
        abort_unless(static::canAccess(), 403);
        $view = $this->app()->listViews()->whereKey($id)->first();

        if ($view !== null) {
            $user = auth()->user();
            app(ListViewService::class)->copy($view, $user instanceof User ? $user->id : null);
            Notification::make()->title('一覧を複製しました')->success()->send();
        }
    }

    /** @param  list<int|string>  $ids  the stored views in their new order */
    public function reorderViews(array $ids): void
    {
        abort_unless(static::canAccess(), 403);
        app(ListViewService::class)->reorder($this->app(), array_map('intval', $ids));
    }

    public function deleteViewAction(): Action
    {
        return Action::make('deleteView')
            ->label('削除')
            ->link()
            ->color('gray')
            ->icon(Heroicon::OutlinedTrash)
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalIconColor('danger')
            ->modalWidth('md')
            ->modalHeading(fn (array $arguments): string => '「'.($this->app()->listViews()->whereKey((int) ($arguments['view'] ?? 0))->value('name') ?? '').'」を削除しますか？')
            ->modalSubmitActionLabel('削除する')
            ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
            ->modalCancelActionLabel('キャンセル')
            ->action(function (array $arguments): void {
                $view = $this->app()->listViews()->whereKey((int) ($arguments['view'] ?? 0))->first();
                if ($view !== null) {
                    app(ListViewService::class)->delete($view);
                    Notification::make()->title('一覧を削除しました')->success()->send();
                }
            });
    }

    protected function app(): BusinessApp
    {
        return static::appModel() ?? throw new LogicException('The app is not registered.');
    }

    protected static function appModel(): ?BusinessApp
    {
        $resource = static::getResource();

        return is_subclass_of($resource, AppResource::class) ? $resource::app() : null;
    }

    /** The app's list page, which defines its columns, default views and fields. */
    protected function listPage(): AppListRecords
    {
        $page = static::getResource()::getPages()['index']->getPage();
        $instance = is_subclass_of($page, AppListRecords::class) ? new $page : null;

        return $instance ?? throw new LogicException(static::getResource().' has no AppListRecords index page.');
    }
}
