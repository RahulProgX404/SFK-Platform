<?php

declare(strict_types=1);

namespace Modules\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Sales\Models\Concerns\IsDocumentLine;

/**
 * A row of 納品内容詳細 (SYSTEM_BRAIN APP_10): 納品日 (= the sale's 納品予定日), 区分 「売上」, 売上No, コードNo (FSC),
 * 品名, 摘要, 数量, 単位, 単価, 税率, 小計, 注番.
 *
 * @property int $id
 * @property int $delivery_note_id
 * @property-read DeliveryNote $deliveryNote
 */
final class DeliveryNoteLine extends Model
{
    use IsDocumentLine;

    /** Columns of a row (legacy order), as kept in the 変更履歴 of the note. */
    public const COLUMNS = ['line_date', 'sales_number', 'is_fsc', 'product_name', 'summary', 'quantity', 'unit', 'unit_price', 'tax_rate', 'amount', 'order_number', 'sale_id'];

    /** 区分 of every row (legacy option list has 「売上」 only). */
    public const KIND = '売上';

    protected $guarded = ['id', 'created_at', 'updated_at'];

    /** @return BelongsTo<DeliveryNote, $this> */
    public function deliveryNote(): BelongsTo
    {
        return $this->belongsTo(DeliveryNote::class);
    }
}
