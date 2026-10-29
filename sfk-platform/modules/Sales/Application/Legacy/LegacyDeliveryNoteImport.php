<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Legacy;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Models\DeliveryNote;

/**
 * 10. 納品書管理(売上) (legacy app 15): 納品書番号, 売上日付, 請求予定日, the address the note went to (更新納品先 / 郵便番号)
 * and the 納品内容詳細 rows (LegacySalesDocuments).
 */
final class LegacyDeliveryNoteImport extends LegacySalesDocuments
{
    public function legacyAppId(): string
    {
        return '15';
    }

    public function label(): string
    {
        return '10. 納品書管理(売上)';
    }

    public function step(): int
    {
        return 160;
    }

    protected function model(): string
    {
        return DeliveryNote::class;
    }

    protected function table(): string
    {
        return 'delivery_notes';
    }

    protected function linesTable(): string
    {
        return 'delivery_note_lines';
    }

    protected function foreignKey(): string
    {
        return 'delivery_note_id';
    }

    protected function idLabel(): string
    {
        return '納品書管理ID';
    }

    protected function attributes(LegacyRecord $record, int $customerId, string $label, LegacyImportLog $log): array
    {
        return [
            'number' => $record->text('納品書番号') ?? str_pad((string) $record->id(), 7, '0', STR_PAD_LEFT),
            'sold_on' => $record->date('売上日付'),
            'billing_due_on' => $record->date('請求予定日'),
            'postal_code' => $record->text('郵便番号'),
            'address' => $record->text('更新納品先') ?? $record->text('納品先'),
            'closing_day' => ClosingDay::tryFrom((string) $record->text('請求締日'))?->value,
        ];
    }

    protected function lines(LegacyRecord $record, string $label, LegacyImportLog $log): array
    {
        $lines = [];
        foreach ($record->table('納品内容詳細') as $row) {
            if ($row->isBlank('品名', '売上No', '小計', '数量')) {
                continue;
            }
            $lines[] = [
                'sort' => count($lines),
                'sale_id' => $this->sale($row->text('売上No'), $label, $log),
                'line_date' => $row->date('納品日_0'),
                'sales_number' => $row->text('売上No'),
                'is_fsc' => in_array(SaleChoices::FSC_CODE, $row->choices('コードNo'), true),
                'product_name' => $row->text('品名'),
                'summary' => $row->text('摘要'),
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
        foreach (['納品金額合計_税抜_' => 'subtotal', '消費税合計' => 'tax_total'] as $code => $column) {
            $stored = $record->int($code);
            $derived = (int) $document->getAttribute($column);
            if ($stored !== null && $stored !== $derived) {
                $log->warn("{$label}: {$code} {$stored} は再計算で {$derived} になりました");
            }
        }
    }
}
