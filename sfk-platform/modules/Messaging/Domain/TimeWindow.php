<?php

declare(strict_types=1);

namespace Modules\Messaging\Domain;

use DateTimeInterface;

/**
 * Company policy for changing a sent message: the sender may edit / unsend it for so many minutes (null = always).
 */
final readonly class TimeWindow
{
    public function __construct(public ?int $minutes) {}

    public function isOpen(DateTimeInterface $sentAt, DateTimeInterface $now): bool
    {
        if ($this->minutes === null || $this->minutes <= 0) {
            return $this->minutes === null;
        }

        return $now->getTimestamp() - $sentAt->getTimestamp() <= $this->minutes * 60;
    }
}
