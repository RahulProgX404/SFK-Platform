<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Purchases;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Modules\Purchasing\Models\Purchase;

/**
 * 「仕入原価超過があります。」 (legacy record notification of 05, BR-PU-05): a purchase saved with 仕入原価超過 checked
 * notifies its 通知先 (not the user who saved it) — when it is added with them or they changed.
 */
final class OverrunAlert
{
    public const MESSAGE = '仕入原価超過があります。';

    /** @param  string  $url  the purchase's record page */
    public function send(Purchase $purchase, ?User $actor, string $subject, string $url): void
    {
        if (array_filter((array) $purchase->overruns) === []) {
            return;
        }

        $recipients = $purchase->notifiedUsers()->get()->reject(fn (User $user): bool => $actor !== null && $user->is($actor));
        if ($recipients->isEmpty()) {
            return;
        }

        Notification::make()
            ->title($subject)
            ->body(self::MESSAGE.'（'.implode('・', (array) $purchase->overruns).'）')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->iconColor('warning')
            ->actions([Action::make('open')->label('レコードを開く')->url($url)->markAsRead()])
            ->sendToDatabase($recipients);
    }
}
