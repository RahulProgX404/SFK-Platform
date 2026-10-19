<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\MasterData\Models\Partner;

/**
 * A row of 用紙情報 (SYSTEM_BRAIN APP_04): paper bought from a supplier, priced per sheet or per kg (BR-OR-02), and its
 * cutting (BR-OR-03). The amounts are calculated (Domain JobCosting::paperRow()); 略名 / 得意先ID come from the
 * supplier. 入力方法 「手動」 switches on the manual amount, which then replaces the calculated one (BR-OR-04).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $supplier_id
 * @property string|null $pricing
 * @property bool $manual
 * @property int|null $manual_amount
 * @property bool $cutting_manual
 * @property int|null $cutting_manual_amount
 * @property string|null $cut
 * @property string|null $legacy_cut
 * @property-read Partner|null $supplier
 * @property-read Order $order
 */
final class OrderPaper extends Model
{
    /** Columns of a row (in legacy order), as kept in the 変更履歴 of the order. */
    public const COLUMNS = [
        'supplier_id', 'paper_name', 'size', 'weight_kg', 'grain', 'sheets', 'pricing', 'unit_price', 'manual', 'manual_amount',
        'cut', 'bundles', 'cutting_unit_price', 'cutting_manual', 'cutting_manual_amount', 'legacy_cut',
    ];

    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:4',
            'sheets' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'manual' => 'boolean',
            'manual_amount' => 'integer',
            'bundles' => 'decimal:4',
            'cutting_unit_price' => 'decimal:4',
            'cutting_manual' => 'boolean',
            'cutting_manual_amount' => 'integer',
            'legacy_cut' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        self::saving(function (self $row): void {
            if (! $row->manual) {
                $row->manual_amount = null;
            }
            if (! $row->cutting_manual) {
                $row->cutting_manual_amount = null;
            }
        });
    }

    /** 切(帳票用): 切, else the old numeric 切_旧. */
    public function cutForPrint(): ?string
    {
        if (filled($this->cut)) {
            return $this->cut;
        }

        return $this->legacy_cut === null ? null : rtrim(rtrim($this->legacy_cut, '0'), '.');
    }

    /** @return BelongsTo<Partner, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'supplier_id');
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
