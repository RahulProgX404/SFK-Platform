<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Domain\LeaveRequests\LeaveRequestStatus;
use Modules\HumanResources\Models\LeaveRequest;

/**
 * Takes over 16. 申請管理 (legacy app 23, SYSTEM_BRAIN APP_16, BP-10): 申請管理ID kept, 申請区分, 申請者 (a request
 * without one is skipped), 作業員 (02 by name), 申請分類, 対象日付, the times of 遅刻・早退, 申請理由 and the
 * ステータス of the process management (申請前 / 第一申請中 / 承認済).
 */
final class LegacyLeaveRequestImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '23';
    }

    public function label(): string
    {
        return '16. 申請管理';
    }

    public function step(): int
    {
        return 220;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $label = '申請管理ID '.$record->id();
                $applicantId = $this->users->id($record->user('申請者'));
                if ($applicantId === null) {
                    $log->skipped("{$label}: 申請者がいません");

                    continue;
                }

                $worker = $record->text('作業員名');
                $workerId = $this->users->named($worker)?->id;
                if ($worker !== null && $workerId === null) {
                    $log->warn("{$label}: 作業員「{$worker}」が見つかりません");
                }

                $status = $record->text('ステータス');
                if ($status !== null && LeaveRequestStatus::tryFrom($status) === null) {
                    $log->warn("{$label}: ステータス「{$status}」は申請前にしました");
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('leave_requests', $record->id(), [
                    'request_type' => $log->keptChoice($record->text('申請区分'), HrChoices::REQUEST_TYPES, $label, '申請区分') ?? HrChoices::REQUEST_TYPES[0],
                    'applicant_id' => $applicantId,
                    'worker_id' => $workerId,
                    'category' => $log->keptChoice($record->text('申請分類'), HrChoices::CATEGORIES, $label, '申請分類') ?? HrChoices::CATEGORIES[0],
                    'target_on' => $record->date('対象日付') ?? substr((string) $record->createdAt(), 0, 10),
                    'total_time' => $record->time('遅刻・早退_合計時間'),
                    'clock_time' => $record->time('遅刻・早退_出勤・退勤時間'),
                    'reason' => $record->text('申請理由') ?? '',
                    'status' => (LeaveRequestStatus::tryFrom((string) $status) ?? LeaveRequestStatus::Draft)->value,
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                if ($created) {
                    $this->rows->startHistory(LeaveRequest::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('leave_requests');
        });
    }
}
