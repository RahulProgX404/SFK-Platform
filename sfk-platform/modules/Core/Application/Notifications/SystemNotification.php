<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Illuminate\Notifications\Notification;

/**
 * The stored 通知 (database channel) in the format of the bell (Filament database notifications), plus `kind` and
 * `once` (Notifier). Written synchronously (notifyNow), so it belongs to the transaction that caused it.
 */
final class SystemNotification extends Notification
{
    /** @param  array<string, mixed>  $data */
    public function __construct(public readonly array $data) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return $this->data;
    }

    public function databaseType(object $notifiable): string
    {
        return (string) ($this->data['kind'] ?? self::class);
    }
}
