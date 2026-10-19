<?php

declare(strict_types=1);

namespace Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\MasterData\Models\Partner;
use Modules\Orders\Models\Concerns\IsProcessRow;

/**
 * A row of トムソン・NO・シルク・箔 (SYSTEM_BRAIN APP_04): 金額 = 単価 × 通し数 (BR-OR-03, Domain
 * JobCosting::finishingRow()).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $supplier_id
 * @property bool $manual
 * @property int|null $manual_amount
 * @property-read Partner|null $supplier
 */
final class OrderFinishing extends Model
{
    use IsProcessRow;

    protected $guarded = ['id', 'created_at', 'updated_at'];
}
