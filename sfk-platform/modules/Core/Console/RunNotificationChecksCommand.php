<?php

declare(strict_types=1);

namespace Modules\Core\Console;

use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Modules\Core\Application\Notifications\NotificationCheck;
use Modules\Core\Application\Notifications\NotificationKinds;
use Throwable;

/**
 * The daily 通知 (NotificationKind::$check), scheduled every morning by Core. One failing check is reported and does
 * not keep the others from running.
 */
final class RunNotificationChecksCommand extends Command
{
    protected $signature = 'core:notification-checks {--date= : 基準日 (Y-m-d), default today}';

    protected $description = 'Send the daily notifications (orders due, …)';

    public function handle(NotificationKinds $kinds): int
    {
        $today = CarbonImmutable::parse((string) ($this->option('date') ?: 'today'))->startOfDay();
        $failed = false;

        foreach ($kinds->all() as $kind) {
            if ($kind->check === null) {
                continue;
            }

            try {
                $check = app($kind->check);
                if ($check instanceof NotificationCheck) {
                    $check->run($today);
                    $this->line("{$kind->key}: ok");
                }
            } catch (Throwable $e) {
                report($e);
                $this->error("{$kind->key}: {$e->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
