<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Modules\Core\Access\UserType;
use Modules\Core\Models\NotificationSetting;

/**
 * Sends 通知 (the bell in the top bar of every dashboard, and an e-mail to who asked for it) — the one way modules
 * notify users (legacy app / record notifications, SYSTEM_BRAIN 11_PERMISSIONS §3, 16_REBUILD_SPEC "Notifications").
 *
 * A recipient is left out when they
 * - are the user who caused it ($actor) — they know already;
 * - can no longer sign in (退職 …);
 * - switched the kind off in プロフィール → 通知設定;
 * - may not open the record the 通知 is about (app permissions, レコードのアクセス権 — as the legacy system notified only
 *   users who could see the record), so nothing of a record reaches a user who may not see it;
 * - already have the 通知 of a Notice::once() key.
 *
 * The 通知 is written at once, in the caller's transaction (rolled back with it); the e-mail is queued after the commit.
 */
final class Notifier
{
    public function __construct(
        private readonly NotificationKinds $kinds,
        private readonly RecordDirectory $records,
    ) {}

    /**
     * @param  iterable<User|null>  $recipients
     * @return list<User> who received it
     */
    public function send(Notice $notice, iterable $recipients, ?User $actor = null): array
    {
        $kind = $this->kinds->get($notice->kind);
        $users = $this->recipients($notice, $recipients, $actor);
        if ($users === []) {
            return [];
        }

        $record = $notice->record();
        $title = $notice->titleWith($record !== null ? $this->records->appName($record) : null);
        $url = $notice->urlWith($record !== null ? $this->records->url($record) : null);

        $notification = Notification::make()
            ->title($title)
            ->body($notice->bodyText())
            ->icon($kind->icon)
            ->iconColor($kind->color)
            ->actions($url !== null ? [Action::make('open')->label('レコードを開く')->url($url)->markAsRead()] : []);
        $data = [...$notification->getDatabaseMessage(), 'kind' => $kind->key, 'once' => $notice->onceKey()];

        $mail = $this->settings($users, $kind->key)->filter(fn (NotificationSetting $setting): bool => $setting->mail)->keys()->all();
        foreach ($users as $user) {
            $user->notifyNow(new SystemNotification($data), ['database']);
            if (in_array($user->id, $mail, true) && $user->notificationEmail() !== null) {
                $user->notify(new SystemNotificationMail($title, $notice->bodyText(), $url));
            }
        }

        return $users;
    }

    /** @return EloquentCollection<int, User> ユーザー種別 管理者 and the super admin, the recipients of the administration 通知 */
    public function administrators(): EloquentCollection
    {
        return User::query()->whereIn('user_type', [UserType::Admin->value, UserType::SuperAdmin->value])->orderBy('id')->get();
    }

    /**
     * @param  iterable<User|null>  $recipients
     * @return list<User>
     */
    private function recipients(Notice $notice, iterable $recipients, ?User $actor): array
    {
        $kind = $this->kinds->get($notice->kind);
        $users = collect($recipients)
            ->filter(fn (?User $user): bool => $user instanceof User)
            ->unique('id')
            ->reject(fn (User $user): bool => $actor !== null && $user->is($actor))
            ->filter(fn (User $user): bool => $user->employment_status->canSignIn())
            ->filter(fn (User $user): bool => ! $kind->forAdministrators || $user->user_type->isAdministrator())
            ->values();
        if ($users->isEmpty()) {
            return [];
        }

        $off = $this->settings($users->all(), $kind->key)->reject(fn (NotificationSetting $setting): bool => $setting->receive)->keys()->all();
        $users = $users->reject(fn (User $user): bool => in_array($user->id, $off, true));

        if (($once = $notice->onceKey()) !== null && $users->isNotEmpty()) {
            $had = DatabaseNotification::query()
                ->where('notifiable_type', (new User)->getMorphClass())
                ->whereIn('notifiable_id', $users->pluck('id')->all())
                ->where('data->once', $once)
                ->pluck('notifiable_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
            $users = $users->reject(fn (User $user): bool => in_array($user->id, $had, true));
        }

        $record = $notice->record();
        if ($record !== null) {
            $users = $users->filter(fn (User $user): bool => $this->records->visibleTo($user, $record));
        }

        return $users->values()->all();
    }

    /**
     * @param  list<User>  $users
     * @return Collection<int, NotificationSetting> by user id
     */
    private function settings(array $users, string $kind): Collection
    {
        return NotificationSetting::query()
            ->where('kind', $kind)
            ->whereIn('user_id', array_map(fn (User $user): int => $user->id, $users))
            ->get()
            ->keyBy('user_id');
    }
}
