<?php

declare(strict_types=1);

namespace Modules\Production\Application\Legacy;

use Modules\Core\Application\Legacy\LegacyRecord;

/** 08. 日報入力 (legacy app 13): one record per task, 日報入力ID kept (LegacyDailyReports). */
final class LegacyDailyReportImport extends LegacyDailyReports
{
    public function legacyAppId(): string
    {
        return '13';
    }

    public function label(): string
    {
        return '08. 日報入力';
    }

    public function step(): int
    {
        return 80;
    }

    protected function tasks(LegacyRecord $record): iterable
    {
        yield [$record->id(), '日報入力ID '.$record->id(), $record, $record];
    }
}
