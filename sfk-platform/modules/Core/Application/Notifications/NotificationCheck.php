<?php

declare(strict_types=1);

namespace Modules\Core\Application\Notifications;

use Carbon\CarbonImmutable;

/**
 * A daily look for something users must know about (e.g. orders due tomorrow), run by `core:notification-checks`
 * (scheduled every morning). It sends through the Notifier; give each 通知 a Notice::once() key so a check run twice
 * on a day — or a condition lasting several days — does not send it again.
 */
interface NotificationCheck
{
    public function run(CarbonImmutable $today): void;
}
