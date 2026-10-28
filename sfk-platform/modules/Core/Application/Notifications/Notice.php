<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * One 通知 to send with the Notifier: its kind, what it is about and where it leads.
 *
 *     Notice::make('purchasing.purchase.overrun')->about($purchase, $purchase->purchase_number)->body('仕入原価超過があります。')
 *
 * about() names the record: the title becomes 「<app name>: <name>」, the link its record page, and only users who may
 * open the record receive it. A Notice without a record (title() + url()) goes to every recipient given.
 */
final class Notice
{
    private ?string $title = null;

    private ?string $body = null;

    private ?Model $record = null;

    private ?string $recordName = null;

    private ?string $url = null;

    private ?string $once = null;

    private function __construct(public readonly string $kind) {}

    public static function make(string $kind): self
    {
        return new self($kind);
    }

    /** The record the 通知 is about (title, link and who may receive it); $name is how the record is called. */
    public function about(Model $record, ?string $name = null): self
    {
        $this->record = $record;
        $this->recordName = $name;

        return $this;
    }

    /** The title in place of 「<app name>: <name>」. */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function body(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /** The link in place of the record page. */
    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /** Sent once per user and key: a user who already has a 通知 with this key (read or not) does not get it again. */
    public function once(string $key): self
    {
        $this->once = $key;

        return $this;
    }

    public function record(): ?Model
    {
        return $this->record;
    }

    public function onceKey(): ?string
    {
        return $this->once;
    }

    public function bodyText(): ?string
    {
        return $this->body;
    }

    public function titleWith(?string $appName): string
    {
        if ($this->title !== null) {
            return $this->title;
        }

        $name = $this->recordName ?? ($this->record !== null ? (string) $this->record->getKey() : '');

        return $appName !== null && $appName !== '' ? $appName.': '.$name : $name;
    }

    public function urlWith(?string $recordUrl): ?string
    {
        return $this->url ?? $recordUrl;
    }
}
