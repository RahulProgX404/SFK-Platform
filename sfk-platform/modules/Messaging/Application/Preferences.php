<?php

declare(strict_types=1);

namespace Modules\Messaging\Application;

use App\Models\User;
use Modules\Messaging\Domain\Availability;
use Modules\Messaging\Models\MessagingPreference;

/**
 * メッセージ設定 of a user: 在席状況 (自動 / 離席中 / 取り込み中), 通知音, ブラウザ通知, 通知に本文を表示 (off by default: a toast or a browser notification on a shared
 * screen says only who wrote), オンライン状態を表示, おやすみモード (do not disturb until a time).
 */
final class Preferences
{
    public function for(User $user): MessagingPreference
    {
        return MessagingPreference::query()->firstOrNew(['user_id' => $user->id]);
    }

    /** @param  array<string, mixed>  $values */
    public function update(User $user, array $values): MessagingPreference
    {
        $preference = $this->for($user);
        foreach (['sound', 'desktop', 'preview', 'presence'] as $key) {
            if (array_key_exists($key, $values)) {
                $preference->{$key} = (bool) $values[$key];
            }
        }
        if (array_key_exists('status', $values) && ($status = Availability::tryFrom((string) $values['status'])) !== null) {
            $preference->status = $status;
        }
        if (array_key_exists('dnd_hours', $values)) {
            $hours = (int) $values['dnd_hours'];
            $preference->dnd_until = $hours > 0 ? now()->addHours(min($hours, 24 * 7)) : null;
        }
        $preference->save();
        Signals::hidePresence($user->id, ! $preference->presence);
        Signals::setStatus($user->id, $preference->status);

        return $preference;
    }

    /** @return array{sound: bool, desktop: bool, preview: bool, presence: bool, dnd_until: string|null, status: string} */
    public static function toArray(MessagingPreference $preference): array
    {
        return [
            'sound' => $preference->sound,
            'desktop' => $preference->desktop,
            'preview' => $preference->preview,
            'presence' => $preference->presence,
            'dnd_until' => $preference->dnd_until?->isFuture() ? $preference->dnd_until->toIso8601String() : null,
            'status' => $preference->status->value,
        ];
    }
}
