<?php

declare(strict_types=1);

namespace Modules\Production\Application\Legacy;

use Modules\Core\Application\Legacy\LegacyRecord;

/**
 * 08N. 【新】日報入力 (legacy app 43, not rebuilt as an app — roadmap Phase 5): one record per worker and day with a row
 * per task in 日報詳細; each row becomes a daily report of the new 08 (LegacyDailyReports). The rows keep their legacy
 * number as ROW_OFFSET + the row id of 日報詳細, apart from the record numbers of 08.
 */
final class LegacyNewDailyReportImport extends LegacyDailyReports
{
    public const ROW_OFFSET = 1_000_000_000;

    public function legacyAppId(): string
    {
        return '43';
    }

    public function label(): string
    {
        return '08N. 【新】日報入力';
    }

    public function step(): int
    {
        return 85;
    }

    protected function keepsIds(): bool
    {
        return false;
    }

    protected function tasks(LegacyRecord $record): iterable
    {
        foreach ($record->table('日報詳細') as $index => $row) {
            if ($row->isBlank('開始時間_0', '終了時間_0', '注番_0', '出来上_0', '機械名')) {
                continue;
            }

            $rowId = (int) ($row->rowId() ?? 0);
            if ($rowId === 0) {
                continue;
            }

            yield [self::ROW_OFFSET + $rowId, '日報入力ID '.$record->id().' 日報詳細 '.($index + 1).'行目', $record, $row];
        }
    }
}
