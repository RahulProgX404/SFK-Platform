<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The e-mail copy of a 通知, for users who switched メール on for its kind (プロフィール → 通知設定). Queued, and only
 * after the transaction that caused it committed.
 */
final class SystemNotificationMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $title,
        public readonly ?string $body,
        public readonly ?string $url,
    ) {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【'.config('app.name').'】'.$this->title)
            ->markdown('core::mail.notification', [
                'name' => $notifiable instanceof User ? $notifiable->name : '',
                'title' => $this->title,
                'body' => $this->body,
                'url' => $this->url,
            ]);
    }
}
