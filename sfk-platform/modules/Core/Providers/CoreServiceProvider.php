<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use App\Models\User;
use App\Modules\ModuleServiceProvider;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Livewire\Notifications;
use Filament\Notifications\View\NotificationsIconAlias;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsIconAlias;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\ComponentHookRegistry;
use Modules\Core\Access\DashboardAccess;
use Modules\Core\Application\Apps\AppDiscovery;
use Modules\Core\Application\Cache\DataCache;
use Modules\Core\Application\Cache\ReferenceCache;
use Modules\Core\Application\Comments\RecordComments;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Core\Application\Notifications\RecordDirectory;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Core\Application\Search\SearchQuery;
use Modules\Core\Console\ImportLegacyDataCommand;
use Modules\Core\Console\PruneNotificationsCommand;
use Modules\Core\Console\RunNotificationChecksCommand;
use Modules\Core\Console\SyncAppsCommand;
use Modules\Core\Console\SyncSuperAdminCommand;
use Modules\Core\Filament\AppPages\AppListRecords;
use Modules\Core\Filament\Records\RecordTabs;
use Modules\Core\Filament\Records\RelatedLists;
use Modules\Core\Filament\Support\AppRecordDirectory;
use Modules\Core\Filament\Users\UserDetailWidgets;
use Modules\Core\Http\Portal\DashboardDirectory;
use Modules\Core\Http\Portal\DashboardEntry;
use Modules\Core\Http\Responses\LoginPageLogoutResponse;
use Modules\Core\Livewire\StripsIndentation;
use Modules\Core\Models\AppFieldPermission;
use Modules\Core\Models\AppListView;
use Modules\Core\Models\AppPermission;
use Modules\Core\Models\AppRecordRule;
use Modules\Core\Models\AppRecordRuleEntry;
use Modules\Core\Models\BusinessApp;
use Modules\Core\Models\DocumentOutput;
use Modules\Core\Models\DocumentTemplate;
use Modules\Core\Models\EmployeeCategory;
use Modules\Core\Models\Position;
use Modules\Core\Models\Space;
use Modules\Core\Models\SpacePermission;

final class CoreServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'core';

    protected array $commands = [
        SyncSuperAdminCommand::class,
        SyncAppsCommand::class,
        RunNotificationChecksCommand::class,
        PruneNotificationsCommand::class,
        ImportLegacyDataCommand::class,
    ];

    protected function registerModule(): void
    {
        // every Livewire component without the indentation of its HTML (speed; Http\HtmlIndentation). Here, not in
        // boot: Livewire wires the component hooks known when it boots
        ComponentHookRegistry::register(StripsIndentation::class);

        $this->app->singleton(DocumentNumberGenerator::class);
        $this->app->singleton(RelatedLists::class);
        $this->app->singleton(RecordTabs::class);
        // cards of other modules on the page of a user (e.g. 個人の概要)
        $this->app->singleton(UserDetailWidgets::class);

        // 移行: the legacy logins are looked up once for all the apps of an import (legacy:import)
        $this->app->singleton(LegacyUsers::class);
        $this->app->singleton(LegacyPartners::class);

        // 通知: the kinds the modules register, the sender, and the records seen through the app screens
        $this->app->singleton(NotificationKinds::class);
        $this->app->singleton(RecordDirectory::class, AppRecordDirectory::class);
        $this->app->singleton(Notifier::class);

        // Apps are discovered from the AppResource screens of every module (enabled or not).
        $this->app->bind(AppDiscovery::class, fn (): AppDiscovery => new AppDiscovery([base_path('modules')]));

        // Dashboards have no login page of their own: logging out returns to the single /login page.
        $this->app->bind(LogoutResponse::class, LoginPageLogoutResponse::class);
    }

    protected function bootModule(): void
    {
        // a change of the registry, the access rights, 一覧 or 帳票 is read afresh on the next request (ReferenceCache)
        foreach ([BusinessApp::class, Space::class, AppPermission::class, SpacePermission::class, AppFieldPermission::class,
            AppRecordRule::class, AppRecordRuleEntry::class, AppListView::class, DocumentOutput::class, DocumentTemplate::class,
            EmployeeCategory::class, Position::class, User::class] as $model) {
            $model::saved(fn () => ReferenceCache::bump());
            $model::deleted(fn () => ReferenceCache::bump());
        }
        // new or changed columns (ReferenceCache 'columns:<table>')
        Event::listen(MigrationsEnded::class, fn () => ReferenceCache::bump());
        // any record saved or deleted: the dashboard's figures are added up afresh (DataCache), throttled, flushed at the end
        Event::listen(['eloquent.saved: *', 'eloquent.deleted: *'], fn () => DataCache::changed());
        Event::listen([JobProcessed::class, JobFailed::class, CommandFinished::class], fn () => DataCache::flush());
        $this->app->terminating(fn () => DataCache::flush());

        // the compared form of the list search (sfk_search_norm) on SQLite connections; PostgreSQL has it from a migration
        Event::listen(ConnectionEstablished::class, fn (ConnectionEstablished $event) => SearchQuery::registerFunction($event->connection));
        foreach (DB::getConnections() as $connection) {
            SearchQuery::registerFunction($connection);
        }

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
            }
        });

        // the search column chosen on the app lists lasts until logout: every login starts from the default
        Event::listen([Login::class, Logout::class], function (): void {
            if (app()->bound('session')) {
                session()->forget(AppListRecords::SEARCH_COLUMNS_SESSION);
            }
        });

        // The dashboard name (sidebar header and topbar) leads to the dashboard's ポータル; the first menu group
        // ダッシュボード切替 lists the dashboards the user may open. Registered once, globally, so they
        // work for every panel regardless of which one booted.
        FilamentView::registerRenderHook(PanelsRenderHook::SIDEBAR_LOGO_AFTER, fn (): string => self::dashboardSwitcher('sidebar'));
        FilamentView::registerRenderHook(PanelsRenderHook::TOPBAR_LOGO_AFTER, fn (): string => self::dashboardSwitcher('topbar'));
        FilamentView::registerRenderHook(PanelsRenderHook::SIDEBAR_NAV_START, fn (): string => self::dashboardMenu());

        // toolbar of the app lists (AppListRecords::toolbarPart(), empty elsewhere): the search column, 絞り込む and the
        // legacy-button switch right of the search box, then 並び替え and ⚙ (the lists have no 列 chooser, so not on its
        // hooks: Filament leaves them out without one); the legacy buttons under it
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_SEARCH_AFTER, fn (): string => AppListRecords::toolbarPart('start')
            .AppListRecords::toolbarPart('sort')
            .AppListRecords::toolbarPart('settings'));
        FilamentView::registerRenderHook(TablesRenderHook::TOOLBAR_AFTER, fn (): string => AppListRecords::toolbarPart('menu'));

        // Toasts: top middle of the screen, a solid icon per status — drawn white on the coloured icon tile of the
        // theme (core::filament.theme), the glyph showing the tile's colour through it; danger has the ✕ circle.
        Notifications::alignment(Alignment::Center);
        Notifications::verticalAlignment(VerticalAlignment::Start);
        FilamentIcon::register([
            NotificationsIconAlias::NOTIFICATION_SUCCESS => Heroicon::CheckCircle,
            NotificationsIconAlias::NOTIFICATION_INFO => Heroicon::InformationCircle,
            NotificationsIconAlias::NOTIFICATION_WARNING => Heroicon::ExclamationTriangle,
            NotificationsIconAlias::NOTIFICATION_DANGER => Heroicon::XCircle,
            // ≡ at the right end of the sidebar's header closes it (sidebar.png, bug.md 2026-11-07)
            PanelsIconAlias::SIDEBAR_COLLAPSE_BUTTON => Heroicon::OutlinedBars3,
        ]);

        // 通知 of a コメント, in every app (RecordComments)
        $this->app->make(NotificationKinds::class)->register(new NotificationKind(
            key: RecordComments::NOTIFICATION,
            label: 'コメント',
            icon: Heroicon::OutlinedChatBubbleLeftEllipsis,
        ));

        // the daily 通知 every morning before work starts; old 通知 pruned at night
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(RunNotificationChecksCommand::class)->dailyAt('07:30')->withoutOverlapping();
            $schedule->command(PruneNotificationsCommand::class)->dailyAt('03:00');
        });

        // Drop-downs search shortly after typing stops (Filament waits a full second).
        Select::configureUsing(fn (Select $select): Select => $select->searchDebounce(300));
    }

    private static function dashboardSwitcher(string $place): string
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return view('core::filament.dashboard-switcher', [
            'place' => $place,
            'currentLabel' => DashboardAccess::label($panel),
            'homeUrl' => $panel->getUrl(),
        ])->render();
    }

    private static function dashboardMenu(): string
    {
        $user = auth()->user();
        $panel = Filament::getCurrentOrDefaultPanel();
        $dashboards = $user instanceof User ? app(DashboardDirectory::class)->for($user) : [];
        if ($dashboards === []) {
            return '';
        }

        // administrator-only dashboards (共通管理) first, then the others in their order
        usort($dashboards, fn (DashboardEntry $a, DashboardEntry $b): int => DashboardAccess::isAdminOnly($b->id) <=> DashboardAccess::isAdminOnly($a->id));

        return view('core::filament.dashboard-menu', [
            'items' => array_map(fn (DashboardEntry $dashboard): NavigationItem => NavigationItem::make($dashboard->label)
                ->url($dashboard->url)
                // the icons of the portal's dashboard cards
                ->icon(match ($dashboard->icon) {
                    'erp' => Heroicon::OutlinedBriefcase,
                    'settings' => Heroicon::OutlinedCog6Tooth,
                    default => Heroicon::OutlinedSquares2x2,
                })
                ->isActiveWhen(fn (): bool => $dashboard->id === $panel->getId()), $dashboards),
        ])->render();
    }
}
