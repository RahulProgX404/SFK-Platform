<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Freight;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Money\Yen;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Application\PurchasingActionNotAllowed;
use Modules\Purchasing\Models\FreightCharge;
use Modules\Purchasing\Models\Purchase;

/**
 * Posts carrier billing data of 15. 運送請求データ取込 as purchases of 05. 納品(仕入)入力 (16_REBUILD_SPEC "freight import";
 * the legacy conversion was manual, SYSTEM_BRAIN APP_15 known issues): one purchase per record of the carrier (仕入先)
 * — 納品(仕入)日 = 日付 (else today), 受注No = 注番, 品名 = 備考 (else 運送代), 数量 / 単価 / the amounts copied, 金額 = 金額
 * (else 数量 × 単価 by the carrier's 端数区分(仕入), BR-PU-02) — and 納品(仕入)入力No written back. Records already posted
 * are left out. The purchases then count in their jobs and the carrier's 買掛 like any other.
 */
final class FreightPurchasePoster
{
    /**
     * @param  iterable<FreightCharge>  $charges
     * @return list<Purchase>
     */
    public function post(iterable $charges, Partner $carrier, ?PurchaseCategory $category = null, ?CarbonImmutable $today = null): array
    {
        if (! $carrier->is_supplier) {
            throw new PurchasingActionNotAllowed('仕入先を選択してください。');
        }

        $today ??= CarbonImmutable::today();
        $rounding = RoundingMode::fromLabel($carrier->supplier_rounding?->value);

        return DB::transaction(function () use ($charges, $carrier, $category, $today, $rounding): array {
            $purchases = [];
            foreach ($charges as $charge) {
                $charge = FreightCharge::query()->lockForUpdate()->find($charge->getKey());
                if ($charge === null || $charge->purchase_id !== null) {
                    continue;
                }

                $amount = $charge->amount;
                if ($amount === null && $charge->quantity !== null && $charge->unit_price !== null) {
                    $amount = Yen::round(Yen::multiply($charge->quantity, $charge->unit_price), $rounding);
                }

                $purchase = Purchase::query()->create([
                    'purchased_on' => ($charge->charged_on ?? $today)->toDateString(),
                    'supplier_id' => $carrier->id,
                    'category' => $category?->value,
                    'order_id' => $charge->order_id,
                    'product_name' => filled($charge->notes) ? $charge->notes : '運送代',
                    'quantity' => $charge->quantity,
                    'unit_price' => $charge->unit_price,
                    'amount' => (int) $amount,
                    'cutting_amount' => (int) $charge->cutting_amount,
                    'plate_amount' => (int) $charge->plate_amount,
                    'stored_plate_amount' => (int) $charge->stored_plate_amount,
                    'spot_color_amount' => (int) $charge->spot_color_amount,
                ]);
                $charge->purchase()->associate($purchase)->save();
                $purchases[] = $purchase;
            }

            return $purchases;
        });
    }
}
