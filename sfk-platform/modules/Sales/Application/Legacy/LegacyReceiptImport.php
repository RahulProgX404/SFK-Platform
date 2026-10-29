<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Models\Receipt;

/**
 * Takes over 21. 売入金管理（売上） (legacy app 33, SYSTEM_BRAIN APP_21, BP-07): レコード番号 kept, the invoice by its
 * 請求書番号 (11 taken over before; one 入金 per invoice — a second one keeps no invoice, reported), 得意先, 請求日 /
 * 請求金額 / 今回締日 as stored, 担当者, 備考 and the 入金明細 rows (入金日, 入金金額, 手数料, 歩引き, 入金方法, 銀行).
 * 入金済 is derived from the rows (BR-AR-04). The receivables follow with 23 / 23-2.
 */
final class LegacyReceiptImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
        private readonly LegacyPartners $partners,
    ) {}

    public function legacyAppId(): string
    {
        return '33';
    }

    public function label(): string
    {
        return '21. 売入金管理（売上）';
    }

    public function step(): int
    {
        return 180;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $invoices = DB::table('invoices')->pluck('id', 'number')->all();

        DB::transaction(function () use ($export, $log, $invoices): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                if ($record->isBlank('得意先レコード番号', '得意先名', '請求書番号_0', '請求金額') && $record->table('入金明細') === []) {
                    $log->skipped("{$label}: 空のレコードのため移行しません");

                    continue;
                }
                $customerId = $this->partners->find($record->int('得意先レコード番号'), $record->exactText('得意先名'));
                if ($customerId === null) {
                    $log->skipped("{$label}: 得意先「{$record->text('得意先名')}」が見つかりません");

                    continue;
                }

                $number = $record->text('請求書番号_0');
                $invoiceId = $number !== null ? ($invoices[$number] ?? null) : null;
                if ($number !== null && $invoiceId === null) {
                    $log->warn("{$label}: 請求書番号「{$number}」の請求書が見つかりません");
                }
                if ($invoiceId !== null && DB::table('receipts')->where('invoice_id', $invoiceId)->where(fn ($query) => $query->whereNull('legacy_id')->orWhere('legacy_id', '!=', $record->id()))->exists()) {
                    $log->warn("{$label}: 請求書「{$number}」の入金は他のレコードにあるため、請求書なしで移行しました");
                    $invoiceId = null;
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('receipts', $record->id(), [
                    'invoice_id' => $invoiceId,
                    'customer_id' => $customerId,
                    'billed_on' => $record->date('請求日'),
                    'billed_amount' => $record->int('請求金額') ?? 0,
                    'closing_day' => ClosingDay::tryFrom((string) $record->text('今回締日'))?->value,
                    'notes' => $record->text('備考'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                $this->rows->replaceRows('receipt_lines', 'receipt_id', $id, $this->lines($record, $label, $log));
                $receipt = Receipt::query()->findOrFail($id);
                $receipt->assignees()->sync(array_values(array_filter(array_map(fn (array $user): ?int => $this->users->id($user), $record->users('担当者')))));
                $receipt->refreshTotals();

                if ($created) {
                    $this->rows->startHistory($receipt, $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('receipts');
        });
    }

    /** @return list<array<string, mixed>> */
    private function lines(LegacyRecord $record, string $label, LegacyImportLog $log): array
    {
        $lines = [];
        foreach ($record->table('入金明細') as $row) {
            if ($row->isBlank('入金日_0', '入金金額', '手数料', '歩引き')) {
                continue;
            }
            $lines[] = [
                'sort' => count($lines),
                'received_on' => $row->date('入金日_0'),
                'amount' => $row->int('入金金額') ?? 0,
                'fee' => $row->int('手数料') ?? 0,
                'discount' => $row->int('歩引き') ?? 0,
                'method' => $log->choice($row->text('入金方法'), SaleChoices::PAYMENT_METHODS, $label, '入金方法'),
                'bank' => $log->keptChoice($row->text('銀行'), SaleChoices::BANKS, $label, '銀行'),
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $lines;
    }
}
