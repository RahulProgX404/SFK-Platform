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
use Modules\HumanResources\Models\AlcoholCheck;

/**
 * Takes over 18. アルコールチェック (legacy app 27, SYSTEM_BRAIN APP_18, BP-10): レコード番号 kept, 検査日, 氏名 (the driver;
 * a check without one is skipped), 従業員 (02 by name) and the two checks 運転前 (fields without suffix) / 運転後
 * (fields 「_0」): 検査時刻, 車両番号, 確認方法, 酒気帯び運転の有無, 確認者, 指示項目, その他必要な項目.
 */
final class LegacyAlcoholCheckImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '27';
    }

    public function label(): string
    {
        return '18. アルコールチェック';
    }

    public function step(): int
    {
        return 240;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $driverId = $this->users->id($record->user('氏名'));
                $employeeId = $this->users->named($record->text('従業員名'))?->id;
                $driverId ??= $employeeId;
                if ($driverId === null) {
                    $log->skipped("{$label}: 氏名がありません");

                    continue;
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('alcohol_checks', $record->id(), [
                    'checked_on' => $record->date('検査日') ?? substr((string) $record->createdAt(), 0, 10),
                    'driver_id' => $driverId,
                    'employee_id' => $employeeId,
                    ...$this->check($record, 'before', '', $label, $log),
                    ...$this->check($record, 'after', '_0', $label, $log),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                if ($created) {
                    $this->rows->startHistory(AlcoholCheck::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('alcohol_checks');
        });
    }

    /** @return array<string, mixed> the columns of 運転前 (before) or 運転後 (after) */
    private function check(LegacyRecord $record, string $block, string $suffix, string $label, LegacyImportLog $log): array
    {
        return [
            $block.'_time' => $record->time('検査時刻'.$suffix),
            $block.'_vehicle' => $log->keptChoice($record->text('車両番号'.$suffix), HrChoices::VEHICLES, $label, '車両番号'),
            $block.'_method' => $log->keptChoice($record->text('確認方法'.$suffix), HrChoices::METHODS, $label, '確認方法'),
            $block.'_detected' => $log->choice($record->text('酒気帯び運転の有無'.$suffix), HrChoices::DETECTED, $label, '酒気帯び運転の有無'),
            $block.'_checker_id' => $this->users->id($record->user('確認者'.$suffix)),
            $block.'_instruction' => $record->text('指示項目'.$suffix),
            $block.'_notes' => $record->text('その他必要な項目'.$suffix),
        ];
    }
}
