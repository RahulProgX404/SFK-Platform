<?php

declare(strict_types=1);

namespace Modules\Messaging\Providers;

use App\Models\User;
use App\Modules\ModuleServiceProvider;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Core\Application\Notifications\NotificationKind;
use Modules\Core\Application\Notifications\NotificationKinds;
use Modules\Core\Application\Notifications\PulseChannels;
use Modules\Core\Application\Notifications\UnreadCounters;
use Modules\Core\Application\Realtime\Realtime;
use Modules\Core\Filament\AppPages\AppSidebarState;
use Modules\Core\Filament\Support\ThemeAssets;
use Modules\Messaging\Application\Announcements;
use Modules\Messaging\Application\Directory;
use Modules\Messaging\Application\Inbox;
use Modules\Messaging\Application\Messages;
use Modules\Messaging\Application\RecordLinks;
use Modules\Messaging\Application\Signals;
use Modules\Messaging\Console\PruneMessagingCommand;
use Modules\Messaging\Console\PublishAnnouncementsCommand;
use Modules\Messaging\Filament\Pages\MessengerPage;
use Modules\Messaging\Models\Participant;

/**
 * 社内メッセージ (docs/messaging.md): the messenger page of the ERP dashboard, its API, the unread badge of every page
 * and the 通知 kinds (メンション, 全社アナウンス).
 */
final class MessagingServiceProvider extends ModuleServiceProvider
{
    protected string $slug = 'messaging';

    protected array $commands = [
        PruneMessagingCommand::class,
        PublishAnnouncementsCommand::class,
    ];

    protected function registerModule(): void
    {
        // per request (a queue worker or Octane must not keep yesterday's people)
        $this->app->scoped(Directory::class);
        $this->app->scoped(RecordLinks::class);
    }

    protected function bootModule(): void
    {
        $perUser = fn (Request $request): string => (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
        RateLimiter::for('messaging-send', fn (Request $request): Limit => Limit::perMinute((int) config('messaging.limits.send', 60))->by('send|'.$perUser($request))
            ->response(fn () => response()->json(['message' => '送信が多すぎます。少し待ってからもう一度送信してください。'], 429)));
        RateLimiter::for('messaging-upload', fn (Request $request): Limit => Limit::perMinute((int) config('messaging.limits.upload', 30))->by('upload|'.$perUser($request))
            ->response(fn () => response()->json(['message' => 'アップロードが多すぎます。少し待ってからもう一度お試しください。'], 429)));
        RateLimiter::for('messaging-write', fn (Request $request): Limit => Limit::perMinute(120)->by('write|'.$perUser($request)));

        $kinds = $this->app->make(NotificationKinds::class);
        $kinds->register(new NotificationKind(key: Messages::NOTIFICATION_MENTION, label: 'メッセージのメンション', icon: Heroicon::OutlinedAtSymbol));
        $kinds->register(new NotificationKind(key: Messages::NOTIFICATION_REPLY, label: 'メッセージへの返信', icon: Heroicon::OutlinedArrowUturnLeft));
        $kinds->register(new NotificationKind(key: Messages::NOTIFICATION_PRIORITY, label: '重要・至急のメッセージ', icon: Heroicon::OutlinedExclamationCircle, color: 'danger'));
        $kinds->register(new NotificationKind(key: Announcements::NOTIFICATION, label: '全社アナウンス', icon: Heroicon::OutlinedMegaphone, color: 'warning'));

        // the unread badge of every page rides on Core's pulse (one request a page serves the bell and the messenger)
        PulseChannels::register('m', fn (int $userId): string => Signals::inboxPulse($userId, request()->boolean('idle')));
        // the portal's 今日 card counts unread messages next to the unread 通知 (the badge's count: messages + アナウンス)
        UnreadCounters::register('messages', 'メッセージ',
            fn (User $user): ?int => self::usesMessenger($user)
                ? $this->app->make(Inbox::class)->unreadTotal($user) + $this->app->make(Announcements::class)->unreadCount($user)
                : null,
            fn (User $user): string => MessengerPage::getUrl(panel: 'erp'));

        // WebSockets: a conversation's channel is open to its current members only (one indexed lookup when a page subscribes)
        Realtime::channel('sfk.conversation.{id}', fn (User $user, int|string $id): bool => Participant::query()
            ->where('conversation_id', (int) $id)->where('user_id', $user->id)->whereNull('left_at')->exists());
        ThemeAssets::extend('messaging::badge-script');
        // the messenger opens with the sidebar closed; its 🏠 (list header) goes back to the dashboard (user 2026-11-18)
        AppSidebarState::collapseOn(MessengerPage::class);
        FilamentView::registerRenderHook(PanelsRenderHook::HEAD_END, fn (): string => self::badgeTag());

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command(PublishAnnouncementsCommand::class)->everyMinute()->withoutOverlapping();
            $schedule->command(PruneMessagingCommand::class)->dailyAt('03:30');
        });
    }

    /** The messenger is a page of the ERP dashboard: users who cannot open that dashboard have none. */
    private static function usesMessenger(User $user): bool
    {
        $panel = Filament::getPanels()['erp'] ?? null;

        return $panel !== null && $user->employment_status->canSignIn() && $user->canAccessPanel($panel);
    }

    /** Where the badge script asks for the unread count, and the messenger's address (no query: two route() calls). */
    private static function badgeTag(): string
    {
        if (! auth()->user() instanceof User) {
            return '';
        }

        return '<meta name="sfk-messaging" content="'.e(route('messaging.unread')).'" data-page="'.e(MessengerPage::getUrl(panel: 'erp')).'">';
    }
}
