<?php

declare(strict_types=1);

namespace Modules\HumanResources\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\HumanResources\Domain\HrChoices;
use Modules\HumanResources\Models\SafetyCheck;

/**
 * Takes over 17. 安全衛生チェック (legacy app 25, SYSTEM_BRAIN APP_17, BP-10): レコード番号 kept, 日付, 名前 (the login;
 * a check without one is skipped), 従業員 (02 by name), 部署, 部署・機械 and the チェックリスト rows (No, 項目, 評価,
 * チェック, 備考).
 */
final class LegacySafetyCheckImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '25';
    }

    public function label(): string
    {
        return '17. 安全衛生チェック';
    }

    public function step(): int
    {
        return 230;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $userId = $this->users->id($record->user('名前'));
                $employee = $record->text('従業員名');
                $employeeId = $this->users->named($employee)?->id;
                $userId ??= $employeeId;
                if ($userId === null) {
                    $log->skipped("{$label}: 名前がありません");

                    continue;
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('safety_checks', $record->id(), [
                    'department_machine' => $record->text('部署・機械'),
                    'department' => $log->keptChoice($record->text('部署'), HrChoices::DEPARTMENTS, $label, '部署') ?? '',
                    'user_id' => $userId,
                    'employee_id' => $employeeId,
                    'checked_on' => $record->date('日付') ?? substr((string) $record->createdAt(), 0, 10),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                $this->rows->replaceRows('safety_check_items', 'safety_check_id', $id, $this->items($record, $label, $log));

                if ($created) {
                    $this->rows->startHistory(SafetyCheck::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('safety_checks');
        });
    }

    /** @return list<array<string, mixed>> */
    private function items(LegacyRecord $record, string $label, LegacyImportLog $log): array
    {
        $items = [];
        foreach ($record->table('チェックリスト') as $row) {
            if ($row->isBlank('No', '項目', '評価')) {
                continue;
            }
            $items[] = [
                'sort' => count($items),
                'number' => $row->decimal('No'),
                'item' => $row->text('項目'),
                'answer' => $log->choice($row->text('評価'), HrChoices::ANSWERS, $label, '評価') ?? HrChoices::ANSWERS[0],
                'checked' => in_array(HrChoices::CHECK, $row->choices('チェック'), true),
                'notes' => $row->text('備考'),
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $items;
    }
}
