<?php

declare(strict_types=1);

namespace Modules\Messaging\Console;

use Illuminate\Console\Command;
use Modules\Messaging\Application\Announcements;

/** Sends the 通知 of 全社アナウンス whose publication time came (scheduled every minute). */
final class PublishAnnouncementsCommand extends Command
{
    protected $signature = 'messaging:announcements';

    protected $description = 'Notify the recipients of scheduled announcements whose time came';

    public function handle(Announcements $announcements): int
    {
        $count = $announcements->notifyDue();
        if ($count > 0) {
            $this->info("{$count} announcement(s) published.");
        }

        return self::SUCCESS;
    }
}
