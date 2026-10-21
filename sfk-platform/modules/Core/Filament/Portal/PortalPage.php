<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Portal;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Modules\Core\Access\AppAccess;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceResource;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DashboardPortal;
use Modules\Core\Models\Space;

/**
 * ポータル: the home page of a dashboard, laid out like the legacy portal — a greeting, the お知らせ with the
 * アプリショートカット (both edited by administrators), and on the right the スペース and アプリ lists; the ⚙ of アプリ
 * (administrators) changes the order of the apps by drag and drop. Everything is scoped to the current dashboard and to
 * what the user may open (spaces they belong to, apps they may view).
 */
final class PortalPage extends Dashboard
{
    protected static ?string $title = 'ポータル';

    protected static ?string $navigationLabel = 'ポータル';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    /** The dashboard name in the sidebar header leads here; the menu starts with ダッシュボード切替 instead. */
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'core::filament.portal';

    /** Full width like the legacy portal. */
    protected Width|string|null $maxContentWidth = Width::Full;

    /** スペース list: "joined" (参加中のスペース) or "all" (すべてのスペース, administrators). */
    public string $spaceScope = 'joined';

    /** アプリ list filter: a space id, or null for すべてのアプリ. */
    public ?int $appSpace = null;

    public function mount(): void
    {
        if (self::isAdministrator()) {
            $this->spaceScope = 'all';
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function updatedSpaceScope(string $value): void
    {
        if ($value !== 'all' || ! self::isAdministrator()) {
            $this->spaceScope = 'joined';
        }
    }

    public function showSpace(int $spaceId): void
    {
        $this->appSpace = $spaceId;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = self::actor();
        $portal = DashboardPortal::for(self::panelId());
        $apps = $this->visibleApps();
        $spaces = $this->spaces();

        return [
            'notice' => filled($portal->notice) ? RichContentRenderer::make($portal->notice)->toHtml() : null,
            'shortcuts' => collect($portal->shortcut_app_ids ?? [])
                ->map(fn (int $id): ?BusinessApp => $apps->firstWhere('id', $id))
                ->filter()
                ->values(),
            'spaces' => $spaces,
            // ⚙ of each space (administrators): its settings (the edit dialog of スペース)
            'spaceSettingsUrls' => self::isAdministrator()
                ? $spaces->mapWithKeys(fn (Space $space): array => [
                    $space->id => SpaceResource::getUrl('index', ['tableAction' => 'edit', 'tableActionRecord' => $space->id]),
                ])->all()
                : [],
            'filterSpaces' => $this->spaces(forFilter: true),
            'apps' => $this->appSpace === null ? $apps : $apps->where('space_id', $this->appSpace)->values(),
            'appUrls' => $apps->mapWithKeys(fn (BusinessApp $app): array => [$app->id => AppResource::urlForApp($app->code, Filament::getCurrentOrDefaultPanel())])->all(),
            'isAdministrator' => self::isAdministrator(),
            'isSuperAdmin' => $user?->isSuperAdmin() ?? false,
            'spacesUrl' => self::isAdministrator() ? SpaceResource::getUrl('index') : null,
            'appsSettingsUrl' => ($user?->isSuperAdmin() ?? false) && Route::has('filament.settings.pages.apps')
                ? route('filament.settings.pages.apps')
                : null,
        ];
    }

    public function editNoticeAction(): Action
    {
        return Action::make('editNotice')
            ->label('お知らせを編集')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => self::isAdministrator())
            ->modalHeading('お知らせを編集')
            ->modalWidth('5xl')
            ->fillForm(fn (): array => ['notice' => DashboardPortal::for(self::panelId())->notice])
            ->schema([
                RichEditor::make('notice')
                    ->hiddenLabel()
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', 'textColor', 'highlight', 'link'],
                        ['h2', 'h3'],
                        ['alignStart', 'alignCenter', 'alignEnd'],
                        ['bulletList', 'orderedList', 'blockquote', 'table'],
                        ['undo', 'redo'],
                    ])
                    ->fileAttachments(false),
            ])
            ->action(function (array $data): void {
                $portal = DashboardPortal::for(self::panelId());
                $portal->fill(['notice' => $data['notice'] ?? null])->save();

                Notification::make()->success()->title('お知らせを保存しました')->send();
            });
    }

    public function editShortcutsAction(): Action
    {
        return Action::make('editShortcuts')
            ->label('アプリショートカットを編集')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->iconButton()
            ->size('sm')
            ->color('gray')
            ->visible(fn (): bool => self::isAdministrator())
            ->modalHeading('アプリショートカットを編集')
            ->fillForm(fn (): array => ['shortcut_app_ids' => DashboardPortal::for(self::panelId())->shortcut_app_ids ?? []])
            ->schema([
                Select::make('shortcut_app_ids')
                    ->label('表示するアプリ')
                    ->multiple()
                    ->options(fn (): array => $this->visibleApps()->mapWithKeys(fn (BusinessApp $app): array => [$app->id => $app->name])->all()),
            ])
            ->action(function (array $data): void {
                $portal = DashboardPortal::for(self::panelId());
                $portal->fill(['shortcut_app_ids' => array_values(array_map('intval', (array) ($data['shortcut_app_ids'] ?? [])))])->save();

                Notification::make()->success()->title('アプリショートカットを保存しました')->send();
            });
    }

    public function reorderAppsAction(): Action
    {
        return Action::make('reorderApps')
            ->label('アプリの並び替え')
            ->icon(Heroicon::OutlinedCog6Tooth)
            ->iconButton()
            ->color('gray')
            ->visible(fn (): bool => self::isAdministrator())
            ->modalHeading('アプリの並び替え')
            ->modalWidth('lg')
            ->modalContent(fn (): View => view('core::filament.portal-app-order', ['apps' => $this->visibleApps()]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('閉じる');
    }

    /**
     * Drop in アプリの並び替え: the apps of this dashboard the administrator sees, in their new order (applies at once).
     *
     * @param  list<int|string>  $ids
     */
    public function saveAppOrder(array $ids): void
    {
        $user = self::actor();
        abort_unless($user !== null && self::isAdministrator(), 403);

        $visible = $this->visibleApps()->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $ids = array_values(array_intersect(array_map('intval', $ids), $visible));

        app(AppLifecycle::class)->reorder($ids, $user);
    }

    /**
     * Apps placed in this dashboard that the user may view (AppAccess), in registry order.
     *
     * @return Collection<int, BusinessApp>
     */
    private function visibleApps(): Collection
    {
        $user = self::actor();
        if ($user === null) {
            return collect();
        }

        // Apps placed in this dashboard's spaces; super admins also see apps built for this dashboard that are not
        // placed yet. Who sees which stage: AppAccess::listsOnDashboard (unpublished ones are dimmed in the view).
        $builtHere = AppResource::appCodesIn(Filament::getCurrentOrDefaultPanel());

        return BusinessApp::query()
            ->with('space')
            ->where(fn ($query) => $query
                ->whereHas('space', fn ($query) => $query->where('panel_id', self::panelId()))
                ->when($user->isSuperAdmin(), fn ($query) => $query->orWhereIn('code', $builtHere)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (BusinessApp $app): bool => AppAccess::listsOnDashboard($user, $app))
            ->values();
    }

    /** @return Collection<int, Space> */
    private function spaces(bool $forFilter = false): Collection
    {
        $user = self::actor();
        if ($user === null) {
            return collect();
        }

        $all = self::isAdministrator() && ($forFilter || $this->spaceScope === 'all');

        return Space::query()
            ->where('panel_id', self::panelId())
            ->when(! $all, fn ($query) => $query->whereHas('users', fn ($query) => $query->whereKey($user->id)))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    private static function panelId(): string
    {
        return Filament::getCurrentOrDefaultPanel()->getId();
    }

    private static function actor(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    private static function isAdministrator(): bool
    {
        return self::actor()?->user_type->isAdministrator() ?? false;
    }
}
