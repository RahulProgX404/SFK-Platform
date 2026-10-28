<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Purchases;

use App\Models\User;
use Modules\Core\Application\Notifications\Notice;
use Modules\Core\Application\Notifications\Notifier;
use Modules\Purchasing\Models\Purchase;

/**
 * 「仕入原価超過があります。」 (legacy record notification of 05, BR-PU-05): a purchase saved with 仕入原価超過 checked
 * notifies its 通知先 (not the user who saved it) — when it is added with them or they changed.
 */
final class OverrunAlert
{
    /** The kind of its 通知 (registered by the module). */
    public const NOTIFICATION = 'purchasing.purchase.overrun';

    public const MESSAGE = '仕入原価超過があります。';

    public function __construct(private readonly Notifier $notifier) {}

    /** @param  string  $url  the purchase's record page */
    public function send(Purchase $purchase, ?User $actor, string $subject, string $url): void
    {
        if (array_filter((array) $purchase->overruns) === []) {
            return;
        }

        $this->notifier->send(
            Notice::make(self::NOTIFICATION)
                ->about($purchase)
                ->title($subject)
                ->body(self::MESSAGE.'（'.implode('・', (array) $purchase->overruns).'）')
                ->url($url),
            $purchase->notifiedUsers()->get(),
            $actor,
        );
    }
}
