<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Payables;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\Payables\PayableCategories;
use Modules\Purchasing\Domain\Payables\PayableRoll;
use Modules\Purchasing\Models\PayableMonth;
use Modules\Purchasing\Models\PaymentLine;
use Modules\Purchasing\Models\Purchase;

/**
 * Keeps 23. 買掛管理 of a supplier in line with its purchases and payments: all months are recalculated from the source
 * rows (PayableRoll) and written as `payable_months`, the hand-entered 消費税端数調整 kept. Replaces the legacy Customine
 * upserts on purchase save (which ran for one user only, D-05), the 再集計 / 一括更新 buttons and the month-creation
 * buttons — and covers what they missed: purchases saved by anyone, a changed date or supplier, deletions, payments.
 *
 * - Purchases count in the month of 納品(仕入)日 with 仕入金額合計 (仕入額), its components and its 仕入カテゴリ判定 column;
 *   payment rows in the month of 支払日 (BR-PU-08).
 * - Tax by the supplier's current 消費税区分 and 端数区分 (BR-TX-07).
 */
final class PayableLedger
{
    public function refresh(?int $supplierId, ?CarbonImmutable $today = null): void
    {
        $supplier = $supplierId === null ? null : Partner::query()->find($supplierId);
        if ($supplier === null) {
            return;
        }

        $today ??= CarbonImmutable::now();
        DB::transaction(function () use ($supplier, $today): void {
            $existing = PayableMonth::query()->where('supplier_id', $supplier->id)->lockForUpdate()->get()->keyBy('year_month');
            $adjustments = $existing->filter(fn (PayableMonth $month): bool => $month->tax_adjustment !== 0)
                ->map(fn (PayableMonth $month): int => $month->tax_adjustment)
                ->all();

            $months = PayableRoll::months(
                $this->purchases($supplier),
                $this->payments($supplier->id),
                $adjustments,
                $supplier->supplier_tax_mode,
                RoundingMode::fromLabel($supplier->supplier_rounding?->value),
                YearMonth::fromDate($today),
            );

            foreach ($months as $key => $figures) {
                $values = [...$figures, 'month_start' => YearMonth::parse($key)->firstDay()->toDateString()];
                $month = $existing->get($key);
                if ($month === null) {
                    (new PayableMonth(['supplier_id' => $supplier->id, 'year_month' => $key, ...$values]))->saveQuietly();
                } else {
                    $month->forceFill($values);
                    if ($month->isDirty()) {
                        $month->saveQuietly();
                    }
                }
            }

            $stale = $existing->keys()->diff(array_keys($months))->all();
            if ($stale !== []) {
                PayableMonth::query()->where('supplier_id', $supplier->id)->whereIn('year_month', $stale)->delete();
            }
        });
    }

    /** @return array<string, array<string, int>> */
    private function purchases(Partner $supplier): array
    {
        $months = [];
        Purchase::query()->where('supplier_id', $supplier->id)->get()->each(function (Purchase $purchase) use (&$months, $supplier): void {
            $purchase->setRelation('supplier', $supplier);
            $key = $purchase->month()->key();
            $figures = [
                'purchase_amount' => $purchase->total,
                'cutting_amount' => $purchase->cutting_amount,
                'plate_amount' => $purchase->plate_amount,
                'stored_plate_amount' => $purchase->stored_plate_amount,
                'spot_color_amount' => $purchase->spot_color_amount,
            ];
            $column = PayableCategories::columnFor($purchase->effectiveCategory());
            if ($column !== null) {
                $figures[$column] = $purchase->total;
            }

            foreach ($figures as $name => $amount) {
                $months[$key][$name] = ($months[$key][$name] ?? 0) + $amount;
            }
        });

        return $months;
    }

    /** @return array<string, int> */
    private function payments(int $supplierId): array
    {
        $sums = [];
        PaymentLine::query()->where('supplier_id', $supplierId)->whereNotNull('withdrawn_on')->get()
            ->each(function (PaymentLine $line) use (&$sums): void {
                $key = $line->month()?->key();
                if ($key !== null) {
                    $sums[$key] = ($sums[$key] ?? 0) + $line->amount;
                }
            });

        return $sums;
    }
}
