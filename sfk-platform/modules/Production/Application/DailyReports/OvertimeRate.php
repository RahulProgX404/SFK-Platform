<?php

declare(strict_types=1);

namespace Modules\Production\Application\DailyReports;

use App\Models\User;
use Closure;
use Modules\Production\Domain\ProductionChoices;
use Modules\Production\Models\DailyReport;

/**
 * 残業単価 of a 日報 (docs/legacy-customizations.md, item 3.1): copied from the worker's 従業員マスタ情報 by user id when the
 * report is saved with a new worker (DailyReport::booted — create and change of 作業者, a snapshot: later rate changes
 * leave past reports alone). A 残業 report needs it — without one its overtime would cost nothing — so the forms refuse
 * it with a visible message (the legacy lookup failed silently). Reports without overtime are never blocked, nor are
 * existing 残業 reports saved again with the same worker.
 */
final class OvertimeRate
{
    public const MISSING = '作業者の残業単価が未設定です。ユーザー管理の従業員マスタ情報で残業単価を設定してください。';

    /**
     * A validation rule for 勤務種別 (`$workerId` = the chosen 作業者).
     *
     * @param  Closure(): mixed  $workerId
     */
    public static function rule(Closure $workerId, ?DailyReport $record = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($workerId, $record): void {
            if ($value !== ProductionChoices::WORK_OVERTIME) {
                return;
            }
            $worker = $workerId();
            $unchanged = $record !== null && $record->isOvertimeTask() && (string) $record->user_id === (string) $worker;
            if (! $unchanged && is_numeric($worker) && User::query()->whereKey((int) $worker)->value('overtime_rate') === null) {
                $fail(self::MISSING);
            }
        };
    }
}
