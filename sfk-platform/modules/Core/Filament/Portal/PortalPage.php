<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Portal;

use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\RichEditor;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFilters;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Url;
use Modules\Core\Access\AppAccess;
use Modules\Core\Application\Apps\AppLifecycle;
use Modules\Core\Application\Apps\AppUsageLog;
use Modules\Core\Filament\AppResource;
use Modules\Core\Filament\DashboardSettings\Resources\Spaces\SpaceResource;
use Modules\Core\Filament\Support\RichEditorTools;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DashboardPortal;
use Modules\Core\Models\Space;

/**
 * The home page of a dashboard (/erp; bug.md 2026-11-05, design_ref/Dashboard.png):
 * - the header: ダッシュボード, 全体 | 個人 (page filter `scope`, users whose 財務分析の範囲 is 全体; whose money the cards
 *   show), the period (date button: presets or two dates — the page filters `from` / `to` every card
 *   follows, last 30 days at first) and 新規作成 (the apps of config('<panel>.dashboard.create') the user may add records
 *   to: a menu, the page itself when there is one, disabled when there is none; an app adding records in a panel
 *   (AppResource::quickCreateComponent, e.g. 08. 日報入力) opens it over the dashboard);
 * - the cards: the widgets the business modules give the panel, in a 12-column grid (each says its width);
 * - アプリ (the button of the top bar, ErpPanelProvider): a panel over the whole screen with a search box, the スペース,
 *   よく使うアプリ (the five apps the user opens most, AppUsageLog) and the apps — in the user's own order, which they
 *   change by dragging an app (`users.app_order`, theirs only; 既定の並びに戻す drops it). Administrators' drag (and the
 *   ⚙) changes the order of the registry instead: the default of everyone without an order of their own.
 * The dashboard's お知らせ is shown on the dashboard selection (/portal, PortalController); its editor opens here
 * (?action=editNotice, back to /portal with return=portal). Everything is scoped to the current dashboard and to what the
 * user may open (spaces they belong to, apps they may view).
 */
final class PortalPage extends Dashboard
{
    use HasFilters;

    protected static ?string $title = 'ダッシュボード';

    protected static ?string $navigationLabel = 'ダッシュボード';

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

    /** 'portal': お知らせを編集 was opened from /portal and goes back there when saved. */
    #[Url(as: 'return')]
    public ?string $returnTo = null;

    public function mount(): void
    {
        if (self::isAdministrator()) {
            $this->spaceScope = 'all';
        }
        if (! $this->validPeriod($this->filters)) {
            $this->setPeriod('30d');
        }
        $this->filters['scope'] = $this->allowedScope($this->filters['scope'] ?? null);
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    /** @return array<string> the whole content area, like the app screens (core::filament.theme `.sfk-app-page`) */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sfk-app-page', 'sfk-dashboard-page'];
    }

    public function getHeader(): View
    {
        return view('core::filament.portal-header', [
            'from' => (string) $this->filters['from'],
            'to' => (string) $this->filters['to'],
            // 全体 | 個人 before the date button: users who see everyone's money (財務分析の範囲 全体)
            'scope' => $this->allowedScope($this->filters['scope'] ?? null),
            'switchesScope' => self::actor()?->seesOverallFinance() ?? false,
            'createItems' => $this->createItems(),
        ]);
    }

    /** The period of a preset of the date button: today, 7d, 30d, month (this month), lastMonth, year (this year). */
    public function setPeriod(string $preset): void
    {
        $today = Carbon::today();
        [$from, $to] = match ($preset) {
            'today' => [$today, $today],
            '7d' => [$today->copy()->subDays(6), $today],
            'month' => [$today->copy()->startOfMonth(), $today],
            'lastMonth' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'year' => [$today->copy()->startOfYear(), $today],
            default => [$today->copy()->subDays(29), $today],
        };
        $this->filters = ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'scope' => $this->allowedScope($this->filters['scope'] ?? null)];
        $this->updatedFilters();
    }

    /** Two dates of the date button (Y-m-d, in any order). */
    public function applyPeriod(string $from, string $to): void
    {
        $period = ['from' => min($from, $to), 'to' => max($from, $to)];
        if ($this->validPeriod($period)) {
            $this->filters = [...$period, 'scope' => $this->allowedScope($this->filters['scope'] ?? null)];
            $this->updatedFilters();
        }
    }

    /**
     * 全体 | 個人 of the header (bug.md 2026-11-06): whose money the cards show — everyone's, or only the sales the user is
     * the 担当者 of. Only users with 財務分析の範囲 全体 choose; 個人のみ always see their own (the cards ask the user).
     */
    public function setScope(string $scope): void
    {
        $this->filters = [...($this->filters ?? []), 'scope' => $this->allowedScope($scope)];
        $this->updatedFilters();
    }

    /** overall | personal: what the user may choose (personal for 個人のみ, overall when nothing valid is chosen). */
    private function allowedScope(mixed $scope): string
    {
        $user = self::actor();
        if ($user !== null && $user->seesFinancialAnalysis() && ! $user->seesOverallFinance()) {
            return User::FINANCE_PERSONAL;
        }

        return $scope === User::FINANCE_PERSONAL ? User::FINANCE_PERSONAL : User::FINANCE_OVERALL;
    }

    /**
     * 新規作成: the apps of the dashboard's setting the user may add records to, label => their new-record page and, for
     * an app adding records in a panel (quickCreateComponents()), its code — 新規作成 opens that panel here.
     *
     * @return array<string, array{url: string, app: string|null}>
     */
    public function createItems(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $quick = $this->quickCreateComponents();
        $items = [];
        foreach ((array) config($panel->getId().'.dashboard.create', []) as $code => $label) {
            $url = AppResource::createUrlForApp((string) $code, $panel);
            if ($url !== null) {
                $items[(string) $label] = ['url' => $url, 'app' => array_key_exists((string) $code, $quick) ? (string) $code : null];
            }
        }

        return $items;
    }

    /**
     * The quick-create panels of the 新規作成 apps the user may add records to (AppResource::quickCreateComponent), app
     * code => component; drawn on the dashboard, closed until 新規作成 opens one.
     *
     * @return array<string, class-string>
     */
    public function quickCreateComponents(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();
        $components = [];
        foreach (array_keys((array) config($panel->getId().'.dashboard.create', [])) as $code) {
            $resource = AppResource::forCode((string) $code);
            $component = $resource !== null && in_array($resource, $panel->getResources(), true) && ! $resource::hasPage('create') && $resource::canCreate()
                ? $resource::quickCreateComponent() : null;
            if ($component !== null) {
                $components[(string) $code] = $component;
            }
        }

        return $components;
    }

    /**
     * The cards of the dashboard: the panel's widgets the user may see, in their order — for the page filters when a
     * card follows them (FollowsDashboardFilters, e.g. the 個人 layout of the money cards).
     *
     * @return list<class-string<Widget>>
     */
    public function dashboardWidgets(): array
    {
        $filters = $this->filters ?? [];
        $sort = fn (string $widget): int => is_subclass_of($widget, FollowsDashboardFilters::class) ? $widget::sortOnDashboard($filters) : $widget::getSort();

        $widgets = array_map(fn (string|WidgetConfiguration $widget): string => $widget instanceof WidgetConfiguration ? $widget->widget : $widget, $this->getWidgets());
        $widgets = array_values(array_filter($widgets, fn (string $widget): bool => $widget::canView()
            && (! is_subclass_of($widget, FollowsDashboardFilters::class) || $widget::shownOnDashboard($filters))));
        usort($widgets, fn (string $a, string $b): int => $sort($a) <=> $sort($b));

        return $widgets;
    }

    /** @param  array<string, mixed>|null  $period */
    private function validPeriod(?array $period): bool
    {
        foreach (['from', 'to'] as $key) {
            $value = $period[$key] ?? null;
            if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1 || Carbon::canBeCreatedFromFormat($value, 'Y-m-d') === false) {
                return false;
            }
        }

        return true;
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
        $apps = $this->visibleApps(personal: true);
        $spaces = $this->spaces();
        $appUrls = $apps->mapWithKeys(fn (BusinessApp $app): array => [$app->id => AppResource::urlForApp($app->code, Filament::getCurrentOrDefaultPanel())])->all();
        $usable = $apps->filter(fn (BusinessApp $app): bool => ($appUrls[$app->id] ?? null) !== null)->keyBy('code');

        return [
            // よく使うアプリ: the five apps the user opens most — before they opened any, those everyone opens most — of
            // the apps they may open (bug.md 2026-11-06)
            'mostUsed' => $user === null ? collect() : collect(app(AppUsageLog::class)->mostUsedOrPopular($user, $usable->keys()->all()))
                ->map(fn (string $code): ?BusinessApp => $usable->get($code))->filter()->values(),
            'spaces' => $spaces,
            // ⚙ of each space (administrators): its settings (the edit dialog of スペース)
            'spaceSettingsUrls' => self::isAdministrator()
                ? $spaces->mapWithKeys(fn (Space $space): array => [
                    $space->id => SpaceResource::getUrl('index', ['tableAction' => 'edit', 'tableActionRecord' => $space->id]),
                ])->all()
                : [],
            'filterSpaces' => $this->spaces(forFilter: true),
            'apps' => $this->appSpace === null ? $apps : $apps->filter(fn (BusinessApp $app): bool => $app->spaces->contains('id', $this->appSpace))->values(),
            'appUrls' => $appUrls,
            'isAdministrator' => self::isAdministrator(),
            // a user (not an administrator) with their own order of the apps: 既定の並びに戻す
            'hasOwnOrder' => ! self::isAdministrator() && $user?->app_order !== null,
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
                    // 文字色 as a palette under the button; the table tools (with 表を削除) in the toolbar
                    ->tools(RichEditorTools::tools())
                    ->toolbarButtons([
                        ['bold', 'italic', 'underline', 'strike', RichEditorTools::TEXT_COLOR, 'highlight', 'link'],
                        ['h2', 'h3'],
                        ['alignStart', 'alignCenter', 'alignEnd'],
                        ['bulletList', 'orderedList', 'blockquote', 'horizontalRule'],
                        RichEditorTools::TABLE_GROUP,
                        ['undo', 'redo'],
                    ])
                    ->floatingToolbars([])
                    ->fileAttachments(false),
                RichEditorTools::palette(),
            ])
            ->action(function (array $data): void {
                $portal = DashboardPortal::for(self::panelId());
                $portal->fill(['notice' => $data['notice'] ?? null])->save();

                Notification::make()->success()->title('お知らせを保存しました')->send();

                if ($this->returnTo === 'portal') {
                    $this->redirect(route('core.portal'));
                }
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
            ->modalContent(fn (): View => view('core::filament.portal-app-order', ['apps' => $this->visibleApps(), 'panelId' => self::panelId()]))
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
     * Drop in the アプリ panel (bug.md 2026-11-06): administrators change the default order (the registry, everyone who has
     * no order of their own); every other user changes only their own order (theirs only). $ids are the apps shown —
     * all, or those of a space — in their new order; they take the places those apps had, the others keep theirs.
     *
     * @param  list<int|string>  $ids
     */
    public function saveMyAppOrder(array $ids): void
    {
        $user = self::actor();
        abort_unless($user !== null, 403);

        if (self::isAdministrator()) {
            $this->saveAppOrder($ids);

            return;
        }

        $current = $this->visibleApps(personal: true)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $moved = array_values(array_intersect(array_map('intval', $ids), $current));
        $places = array_keys(array_filter($current, fn (int $id): bool => in_array($id, $moved, true)));
        foreach ($places as $index => $place) {
            $current[$place] = $moved[$index];
        }

        $user->forceFill(['app_order' => array_values($current)])->save();
    }

    /** 既定の並びに戻す: the user drops their own order — the apps follow the registry order (everyone's default) again. */
    public function resetMyAppOrder(): void
    {
        $user = self::actor();
        abort_unless($user !== null, 403);

        $user->forceFill(['app_order' => null])->save();
        Notification::make()->success()->title('既定の並びに戻しました')->send();
    }

    /**
     * Apps placed in this dashboard that the user may view (AppAccess), in registry order — or, personal, in the user's
     * own order (`users.app_order`; apps not in it after, in registry order). Administrators always see the default
     * order, which their drag changes.
     *
     * @return Collection<int, BusinessApp>
     */
    private function visibleApps(bool $personal = false): Collection
    {
        $user = self::actor();
        if ($user === null) {
            return collect();
        }

        // Apps placed in this dashboard's spaces (an app in several of them is listed once); super admins also see apps
        // built for this dashboard that are not
        // placed yet. Who sees which stage: AppAccess::listsOnDashboard (unpublished ones are dimmed in the view).
        $builtHere = AppResource::appCodesIn(Filament::getCurrentOrDefaultPanel());

        return BusinessApp::query()
            ->with('spaces')
            ->where(fn ($query) => $query
                ->whereHas('spaces', fn ($query) => $query->where('panel_id', self::panelId()))
                ->when($user->isSuperAdmin(), fn ($query) => $query->orWhereIn('code', $builtHere)))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (BusinessApp $app): bool => AppAccess::listsOnDashboard($user, $app))
            ->values()
            ->when($personal && ! $user->user_type->isAdministrator() && $user->app_order !== null, function (Collection $apps) use ($user): Collection {
                $places = array_flip(array_map('intval', (array) $user->app_order));

                return $apps->sortBy(fn (BusinessApp $app, int $index): array => [$places[$app->id] ?? PHP_INT_MAX, $index])->values();
            });
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
