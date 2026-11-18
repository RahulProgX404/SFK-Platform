<?php

declare(strict_types=1);

namespace Modules\Messaging\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Modules\Core\Models\EmployeeCategory;
use Modules\Messaging\Application\Announcements;
use Modules\Messaging\Application\Directory;
use Modules\Messaging\Application\Inbox;
use Modules\Messaging\Application\MessagingAccess;
use Modules\Messaging\Application\Preferences;
use Modules\Messaging\Application\Stickers;
use Modules\Messaging\Http\Controllers\MessengerController;
use Modules\Messaging\Http\MessengerPulse;

/**
 * メッセージ (/erp/messages): the messenger of the ERP dashboard — トーク一覧, the chat, the details panel (three panels on
 * a computer, two on a tablet, one screen at a time on a phone). The page is drawn once with what it needs to show at
 * once (the user, the people, the inbox, settings, stickers: a fixed handful of queries) and then talks to the JSON
 * API (MessengerController) from its own script (Resources/assets/messenger.js, an Alpine component) — no Livewire
 * round trip per click. ?c=<conversation>&m=<message> opens a conversation at a message (通知 links), ?a=<id> an
 * announcement.
 */
final class MessengerPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $navigationLabel = 'メッセージ';

    protected static ?string $title = 'メッセージ';

    protected static ?string $slug = 'messages';

    /** right under 会社カレンダー, above the apps */
    protected static ?int $navigationSort = -90;

    protected string $view = 'messaging::messenger';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->employment_status->canSignIn();
    }

    public function getHeader(): ?View
    {
        return null;
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<string> */
    public function getPageClasses(): array
    {
        return [...parent::getPageClasses(), 'sfk-messenger-page'];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }
        $access = app(MessagingAccess::class);

        return [
            'boot' => [
                'me' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'groups' => $access->canCreateGroups($user),
                    'channels' => $access->canCreateChannels($user),
                    'admin' => $user->user_type->isAdministrator(),
                    'announce' => $access->canAnnounce($user),
                    'stickers' => $access->canManageStickers($user),
                ],
                'people' => MessengerController::peopleFor(app(Directory::class)),
                'conversations' => app(Inbox::class)->list($user),
                'announcementsUnread' => app(Announcements::class)->unreadCount($user),
                'prefs' => Preferences::toArray(app(Preferences::class)->for($user)),
                'stickers' => app(Stickers::class)->catalog(),
                'categories' => EmployeeCategory::query()->where('is_active', true)->orderBy('sort_order')->get(['id', 'name'])->map(fn (EmployeeCategory $category): array => ['id' => $category->id, 'name' => $category->name])->all(),
                'pulse' => MessengerPulse::url($user->id),
                'open' => [
                    'c' => request()->integer('c') ?: null,
                    'm' => request()->integer('m') ?: null,
                    'a' => request()->integer('a') ?: null,
                ],
                'policy' => [
                    'edit' => config('messaging.edit_window_minutes'),
                    'unsend' => config('messaging.unsend_window_minutes'),
                    'maxKb' => (int) config('messaging.attachments.max_kb'),
                    'maxFiles' => (int) config('messaging.attachments.max_files'),
                    'maxLength' => (int) config('messaging.max_message_length'),
                    'types' => array_keys(array_filter((array) config('messaging.attachments.types'), fn (string $kind): bool => $kind !== 'voice')),
                    'voiceSeconds' => (int) config('messaging.attachments.voice_max_seconds'),
                    'active' => (int) config('messaging.pulse.active_ms'),
                    'receipts' => (bool) config('messaging.read_receipts', true),
                    'background' => (int) config('messaging.pulse.background_ms'),
                ],
                'urls' => [
                    'api' => url('messaging/api'),
                    'page' => self::getUrl(),
                    'admin' => MessagingAdminPage::canAccess() ? MessagingAdminPage::getUrl() : null,
                ],
            ],
        ];
    }
}
