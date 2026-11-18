<?php

declare(strict_types=1);

namespace Modules\Messaging\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Messaging\Application\Conversations;
use Modules\Messaging\Application\Directory;
use Modules\Messaging\Application\MessagingAudit;
use Modules\Messaging\Application\MessagingSettings;
use Modules\Messaging\Domain\ConversationType;
use Modules\Messaging\Models\Conversation;
use Modules\Messaging\Models\MessagingAuditLog;
use UnitEnum;

/**
 * メッセージ管理 (administrators: ユーザー種別 管理者 / システム管理者): the company's messenger policy (編集・送信取消の期間,
 * who creates groups / channels, attachment size, retention, 既読 and オンライン状態), the channels (archive / reopen),
 * usage figures and the audit log of administrative actions. It never shows the text of anyone's messages: the
 * figures are counts and sizes, the audit names and ids.
 */
final class MessagingAdminPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'メッセージ管理';

    protected static ?string $title = 'メッセージ管理';

    protected static ?string $slug = 'messages/admin';

    protected static string|UnitEnum|null $navigationGroup = 'システム';

    protected string $view = 'messaging::admin';

    public string $tab = 'settings';

    /** @var array<string, mixed> */
    public array $settings = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->user_type->isAdministrator();
    }

    public function mount(): void
    {
        $this->settings = MessagingSettings::values();
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && self::canAccess(), 403);

        try {
            $changed = MessagingSettings::save($user, $this->settings);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError('settings.'.$key, $messages[0]);
            }

            return;
        }
        $this->settings = MessagingSettings::values();
        Notification::make()->success()->title($changed === [] ? '変更はありません' : '保存しました')->send();
    }

    public function archive(int $channelId, bool $archive): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && self::canAccess(), 403);
        app(Conversations::class)->archiveChannel($user, $channelId, $archive);
        Notification::make()->success()->title($archive ? 'アーカイブしました' : '再開しました')->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return [
            'definitions' => MessagingSettings::DEFINITIONS,
            'channels' => $this->tab === 'channels' ? $this->channels() : [],
            'stats' => $this->tab === 'stats' ? $this->stats() : [],
            'logs' => $this->tab === 'audit' ? $this->logs() : [],
            'labels' => MessagingAudit::LABELS,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function channels(): array
    {
        $directory = app(Directory::class);

        return Conversation::query()
            ->where('type', ConversationType::Channel->value)
            ->withCount('members')
            ->orderBy('archived_at')
            ->orderBy('name')
            ->get()
            ->map(fn (Conversation $channel): array => [
                'id' => $channel->id,
                'name' => $channel->name,
                'kind' => $channel->groupSettings()->kind === 'department' ? '部署' : 'プロジェクト',
                'public' => $channel->groupSettings()->public,
                'readonly' => $channel->groupSettings()->post->value === 'admins',
                'members' => (int) $channel->getAttribute('members_count'),
                'by' => $directory->name($channel->created_by),
                'last' => $channel->last_message_at?->format('Y/m/d H:i'),
                'archived' => $channel->isArchived(),
            ])->all();
    }

    /**
     * Counts and sizes only — never contents.
     *
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $since = now()->subDays(30);
        $recent = DB::table('messages')->where('created_at', '>=', $since)->whereNotNull('user_id');

        $byDepartment = DB::table('messages')
            ->where('messages.created_at', '>=', $since)
            ->join('employee_category_user', 'employee_category_user.user_id', '=', 'messages.user_id')
            ->join('employee_categories', 'employee_categories.id', '=', 'employee_category_user.employee_category_id')
            ->groupBy('employee_categories.name')
            ->selectRaw('employee_categories.name, COUNT(*) AS n')
            ->orderByDesc('n')
            ->limit(15)
            ->pluck('n', 'name')
            ->map(fn (mixed $n): int => (int) $n)
            ->all();

        return [
            'messages' => DB::table('messages')->whereNotNull('user_id')->count(),
            'messages30' => (clone $recent)->count(),
            'conversations30' => (clone $recent)->distinct()->count('conversation_id'),
            'users30' => (clone $recent)->distinct()->count('user_id'),
            'groups' => DB::table('conversations')->where('type', 'group')->count(),
            'channels' => DB::table('conversations')->where('type', 'channel')->whereNull('archived_at')->count(),
            'storage' => (int) DB::table('message_attachments')->sum('size'),
            'unreadAnnouncements' => DB::table('announcement_recipients')
                ->join('announcements', 'announcements.id', '=', 'announcement_recipients.announcement_id')
                ->whereNull('announcement_recipients.read_at')
                ->where('announcements.published_at', '<=', now())
                ->where(fn ($open) => $open->whereNull('announcements.expires_at')->orWhere('announcements.expires_at', '>', now()))
                ->count(),
            'byDepartment' => $byDepartment,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function logs(): array
    {
        $directory = app(Directory::class);

        return MessagingAuditLog::query()
            ->with('conversation:id,name,type')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (MessagingAuditLog $log): array => [
                'at' => $log->created_at->format('Y/m/d H:i'),
                'user' => $directory->name($log->user_id),
                'action' => $log->action,
                'conversation' => $log->conversation !== null ? (string) $log->conversation->name : ($log->conversation_id !== null ? '#'.$log->conversation_id : ''),
                'details' => $this->details($log, $directory),
            ])->all();
    }

    private function details(MessagingAuditLog $log, Directory $directory): string
    {
        $details = (array) ($log->details ?? []);
        $parts = [];
        if (isset($details['users'])) {
            $parts[] = implode('、', array_map(fn (mixed $id): string => $directory->name((int) $id), (array) $details['users']));
        }
        if (isset($details['author'])) {
            $parts[] = '投稿者: '.$directory->name((int) $details['author']);
        }
        if (isset($details['role'])) {
            $parts[] = $details['role'] === 'admin' ? '管理者にする' : '管理者から外す';
        }
        if (isset($details['keys'])) {
            $parts[] = implode('、', array_map(fn (string $key): string => MessagingSettings::DEFINITIONS[$key][2] ?? $key, (array) $details['keys']));
        }
        if (isset($details['recipients'])) {
            $parts[] = '対象 '.$details['recipients'].'人';
        }
        if (isset($details['members'])) {
            $parts[] = 'メンバー '.$details['members'].'人';
        }

        return implode(' / ', $parts);
    }
}
