<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Purchasing\Domain\PurchaseChoices;
use Modules\Purchasing\Models\Payment;

/**
 * Takes over 07. 支払入力 (legacy app 12, SYSTEM_BRAIN APP_07, BP-05): 支払入力ID kept, 支払日 and the 支払内容詳細 rows —
 * 仕入先 (by its 仕入先ID = 得意先ID, else name), 金種名, 支払額, 出金日, 備考. 支払金額合計 is derived from the rows (a
 * difference from the stored one is reported). The payables follow with 23. 買掛管理.
 */
final class LegacyPaymentImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
        private readonly LegacyPartners $partners,
    ) {}

    public function legacyAppId(): string
    {
        return '12';
    }

    public function label(): string
    {
        return '07. 支払入力';
    }

    public function step(): int
    {
        return 130;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        DB::transaction(function () use ($export, $log): void {
            foreach ($export->records() as $record) {
                $label = '支払入力ID '.$record->id();
                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('payments', $record->id(), [
                    'paid_on' => $record->date('支払日') ?? substr((string) $record->createdAt(), 0, 10),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                $this->rows->replaceRows('payment_lines', 'payment_id', $id, $this->lines($record, $label, $log));
                $payment = Payment::query()->findOrFail($id);
                $payment->refreshTotals();
                $stored = $record->int('支払金額合計');
                if ($stored !== null && $stored !== $payment->total) {
                    $log->warn("{$label}: 支払金額合計 {$stored} は支払内容詳細から {$payment->total} になりました");
                }

                if ($created) {
                    $this->rows->startHistory($payment, $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('payments');
        });
    }

    /** @return list<array<string, mixed>> */
    private function lines(LegacyRecord $record, string $label, LegacyImportLog $log): array
    {
        $lines = [];
        foreach ($record->table('支払内容詳細') as $index => $row) {
            if ($row->isBlank('仕入先', '支払額')) {
                continue;
            }

            $supplierId = $this->partners->find($row->int('仕入先ID'), $row->exactText('仕入先'));
            if ($supplierId === null && $row->text('仕入先') !== null) {
                $log->warn("{$label}: ".($index + 1)."行目の仕入先「{$row->text('仕入先')}」が見つかりません");
            }

            $lines[] = [
                'sort' => count($lines),
                'supplier_id' => $supplierId,
                'method' => $log->choice($row->text('金種名'), PurchaseChoices::PAYMENT_METHODS, $label, '金種名') ?? PurchaseChoices::DEFAULT_PAYMENT_METHOD,
                'amount' => $row->int('支払額') ?? 0,
                'withdrawn_on' => $row->date('出金日'),
                'notes' => $row->text('備考'),
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $lines;
    }
}
