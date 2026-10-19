<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Concerns\IsProcessRow;

/**
 * A row of 印刷 (SYSTEM_BRAIN APP_04): 金額 = 通し数 × 色数 × 単価 (BR-OR-03, Domain JobCosting::printingRow()).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $supplier_id
 * @property bool $manual
 * @property int|null $manual_amount
 * @property-read Partner|null $supplier
 */
final class OrderPrinting extends Model
{
    use IsProcessRow;

    protected $guarded = ['id', 'created_at', 'updated_at'];
}
