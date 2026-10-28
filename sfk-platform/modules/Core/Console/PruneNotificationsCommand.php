<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Keeps the bell short: deletes read 通知 after 180 days and every 通知 after a year (scheduled daily by Core).
 */
final class PruneNotificationsCommand extends Command
{
    public const READ_DAYS = 180;

    public const ALL_DAYS = 365;

    protected $signature = 'core:notifications-prune';

    protected $description = 'Delete old notifications';

    public function handle(): int
    {
        $deleted = DatabaseNotification::query()
            ->where(fn ($query) => $query
                ->where('read_at', '<', now()->subDays(self::READ_DAYS))
                ->orWhere('created_at', '<', now()->subDays(self::ALL_DAYS)))
            ->delete();

        $this->line("{$deleted} notifications deleted.");

        return self::SUCCESS;
    }
}
