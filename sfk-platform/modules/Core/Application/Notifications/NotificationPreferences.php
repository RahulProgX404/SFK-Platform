<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Core\Models\NotificationSetting;

/**
 * 通知設定 of a user (プロフィール): which kinds they receive and which also by e-mail. Every kind is received in the bell
 * until switched off; e-mail is off until switched on. A user is offered the kinds they can receive (the administration
 * kinds to administrators only).
 */
final class NotificationPreferences
{
    public function __construct(private readonly NotificationKinds $kinds) {}

    /** @return list<NotificationKind> */
    public function kindsFor(User $user): array
    {
        return array_values(array_filter($this->kinds->all(), fn (NotificationKind $kind): bool => ! $kind->forAdministrators || $user->user_type->isAdministrator()));
    }

    /** @return list<string> keys of the kinds received */
    public function receiving(User $user): array
    {
        $off = $user->notificationSettings()->where('receive', false)->pluck('kind')->all();

        return array_values(array_filter(array_map(fn (NotificationKind $kind): string => $kind->key, $this->kindsFor($user)), fn (string $key): bool => ! in_array($key, $off, true)));
    }

    /** @return list<string> keys of the kinds also sent by e-mail */
    public function mailing(User $user): array
    {
        $offered = array_map(fn (NotificationKind $kind): string => $kind->key, $this->kindsFor($user));

        return array_values(array_intersect($offered, $user->notificationSettings()->where('mail', true)->pluck('kind')->all()));
    }

    /**
     * @param  list<string>  $receive
     * @param  list<string>  $mail  only kinds also received count
     */
    public function save(User $user, array $receive, array $mail): void
    {
        DB::transaction(function () use ($user, $receive, $mail): void {
            foreach ($this->kindsFor($user) as $kind) {
                $receives = in_array($kind->key, $receive, true);
                $mails = $receives && in_array($kind->key, $mail, true);

                if ($receives && ! $mails) {
                    $user->notificationSettings()->where('kind', $kind->key)->delete();
                } else {
                    NotificationSetting::query()->updateOrCreate(['user_id' => $user->id, 'kind' => $kind->key], ['receive' => $receives, 'mail' => $mails]);
                }
            }
        });
    }
}
