<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Legacy;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Models\Invoice;

/**
 * 11. 請求書管理(売上) (legacy app 16): 請求書番号, 請求区分, 請求日付, 今回締日, 請求先 / 郵便番号, the balances the
 * statement showed (前月御請求額, ご入金額 — 繰越金額 and 御請求額 follow, BR-SA-11) and the 請求内容詳細 rows
 * (LegacySalesDocuments). 請求月度 is derived from 請求日付 and 今回締日 (BR-AR-01).
 */
final class LegacyInvoiceImport extends LegacySalesDocuments
{
    public function legacyAppId(): string
    {
        return '16';
    }

    public function label(): string
    {
        return '11. 請求書管理(売上)';
    }

    public function step(): int
    {
        return 170;
    }

    protected function model(): string
    {
        return Invoice::class;
    }

    protected function table(): string
    {
        return 'invoices';
    }

    protected function linesTable(): string
    {
        return 'invoice_lines';
    }

    protected function foreignKey(): string
    {
        return 'invoice_id';
    }

    protected function idLabel(): string
    {
        return '請求書管理ID';
    }

    protected function attributes(LegacyRecord $record, int $customerId, string $label, LegacyImportLog $log): array
    {
        $invoicedOn = $record->date('請求日付');
        $closingDay = ClosingDay::tryFrom((string) ($record->text('今回締日') ?? $record->text('締日_マスタ_')));
        $previous = $record->int('前月御請求額') ?? 0;
        $received = $record->int('ご入金額') ?? 0;

        return [
            'number' => $record->text('請求書番号') ?? str_pad((string) $record->id(), 7, '0', STR_PAD_LEFT),
            'kind' => $log->choice($record->text('請求区分'), SaleChoices::INVOICE_KINDS, $label, '請求区分') ?? SaleChoices::INVOICE_KINDS[0],
            'invoiced_on' => $invoicedOn,
            'closing_day' => $closingDay?->value,
            'billing_month' => $invoicedOn !== null ? BillingCycle::monthFor(CarbonImmutable::parse($invoicedOn), $closingDay)->key() : null,
            'postal_code' => $record->text('郵便番号'),
            'address' => $record->text('請求先'),
            'previous_amount' => $previous,
            'received_amount' => $received,
            'carried_amount' => $previous - $received,
        ];
    }

    protected function lines(LegacyRecord $record, string $label, LegacyImportLog $log): array
    {
        $lines = [];
        foreach ($record->table('請求内容詳細') as $row) {
            if ($row->isBlank('品名', '売上No', '小計', '数量')) {
                continue;
            }
            $lines[] = [
                'sort' => count($lines),
                'sale_id' => $this->sale($row->text('売上No'), $label, $log),
                'line_date' => $row->date('日付'),
                'slip_number' => $row->text('伝票No'),
                'sales_number' => $row->text('売上No'),
                'is_fsc' => in_array(SaleChoices::FSC_CODE, $row->choices('コードNo'), true),
                'product_name' => $row->text('品名'),
                'summary' => $row->text('区分'),
                'quantity' => $row->decimal('数量'),
                'unit' => $row->text('単位'),
                'unit_price' => $row->decimal('単価'),
                'amount' => $row->int('小計') ?? 0,
                'tax_rate' => self::taxRate($row->text('税率_0')),
                'order_number' => $row->text('注番'),
            ];
        }

        return $lines;
    }

    protected function compare(LegacyRecord $record, Model $document, string $label, LegacyImportLog $log): void
    {
        foreach (['今回御買上額_税抜_' => 'subtotal', '今回消費税額' => 'tax_total', '御請求額' => 'billed_total'] as $code => $column) {
            $stored = $record->int($code);
            $derived = (int) $document->getAttribute($column);
            if ($stored !== null && $stored !== $derived) {
                $log->warn("{$label}: {$code} {$stored} は再計算で {$derived} になりました");
            }
        }
    }
}
