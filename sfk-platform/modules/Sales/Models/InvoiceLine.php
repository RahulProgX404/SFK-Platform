<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Sales\Models\Concerns\IsDocumentLine;

/**
 * A row of 請求内容詳細 (SYSTEM_BRAIN APP_11): 日付 (= the sale's 納品予定日), 伝票No (= its 納品書番号), コードNo (FSC),
 * 品名, 数量, 単位, 単価, 税率, 小計, 売上No, 注番. 区分 is 「売上消税」 when there is a 伝票No (BR-SA-17).
 *
 * @property int $id
 * @property int $invoice_id
 * @property string|null $slip_number
 * @property-read Invoice $invoice
 */
final class InvoiceLine extends Model
{
    use IsDocumentLine;

    /** Columns of a row (legacy order), as kept in the 変更履歴 of the invoice. */
    public const COLUMNS = ['line_date', 'slip_number', 'is_fsc', 'product_name', 'summary', 'quantity', 'unit', 'unit_price', 'tax_rate', 'amount', 'sales_number', 'order_number', 'sale_id'];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** 区分 (BR-SA-17) */
    public function kindLabel(): string
    {
        return filled($this->slip_number) ? '売上消税' : '';
    }

    /** 品名 as printed: 品名 + full-width space + 摘要 (BR-SA-17). */
    public function printedName(): string
    {
        return trim(($this->product_name ?? '').(filled($this->summary) ? '　'.$this->summary : ''));
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
