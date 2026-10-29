<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Purchasing\Models\FreightCharge;

/**
 * Takes over 15. 運送請求データ取込 (legacy app 20, SYSTEM_BRAIN APP_15): 運送請求データID kept, 日付, 注番, 運送会社番号,
 * 金額 and the purchase it was posted to (by its 納品(仕入)入力No, 05 taken over before).
 */
final class LegacyFreightImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
    ) {}

    public function legacyAppId(): string
    {
        return '20';
    }

    public function label(): string
    {
        return '15. 運送請求データ取込';
    }

    public function step(): int
    {
        return 110;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $label = '運送請求データID '.$record->id();
                $number = $record->text('注番');
                $orderId = $number !== null ? DB::table('orders')->where('order_number', $number)->value('id') : null;
                if ($number !== null && $orderId === null) {
                    $log->warn("{$label}: 注番「{$number}」の受注が見つかりません");
                }
                $purchaseNumber = $record->text('納品_仕入_入力No');
                $purchaseId = $purchaseNumber !== null ? DB::table('purchases')->where('purchase_number', $purchaseNumber)->value('id') : null;

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('freight_charges', $record->id(), [
                    // 日付 is a text field in the legacy app (e.g. 2025/05/12)
                    'charged_on' => $record->date('日付'),
                    'order_id' => $orderId,
                    'amount' => $record->int('金額'),
                    'carrier_number' => $record->text('運送会社番号'),
                    'purchase_id' => $purchaseId,
                    'unit_price' => $record->decimal('単価'),
                    'quantity' => $record->decimal('数量'),
                    'cutting_amount' => $record->int('断裁金額'),
                    'plate_amount' => $record->int('版代'),
                    'stored_plate_amount' => $record->int('置き版代'),
                    'spot_color_amount' => $record->int('特色'),
                    'notes' => $record->text('備考'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);
                if ($record->text('日付') !== null && $record->date('日付') === null) {
                    $log->warn("{$label}: 日付「{$record->text('日付')}」は日付として読めません");
                }

                if ($created) {
                    $this->rows->startHistory(FreightCharge::query()->findOrFail($id), $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('freight_charges');
        });
    }
}
