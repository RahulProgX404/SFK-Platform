<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Receivables;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Domain\Receivables\ReceivableRoll;
use Modules\Sales\Models\ReceiptLine;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/**
 * Keeps 23. 売掛管理 (calendar) and 23-2. 売掛管理(締日別) (cycle) of a customer in line with its sales and receipts: all
 * months are recalculated from the source rows (ReceivableRoll) and written as `receivable_months`, the hand-entered
 * 消費税端数調整 kept. Replaces the legacy Customine upserts on sales save, the 「再集計」 / 「一括更新」 buttons and the
 * month-creation buttons (records of months without activity while the balance is open, BR-AR-05) — and covers what
 * they missed: receipts saved in 21, deleted sales (D-06: 23-2 not updated), a changed customer or date.
 *
 * - Sales count in the month of 売上日 (calendar) / their 月度 (cycle), receipts rows in the month of 入金日 / its 月度 by
 *   the invoice's 今回締日 (BR-AR-06), with 入金 + 手数料 + 歩引き (BR-AR-04).
 * - Tax per month and rate, rounded by the customer's current 端数区分 (BR-TX-06).
 */
final class ReceivableLedger
{
    public function refresh(?int $customerId, ?CarbonImmutable $today = null): void
    {
        $customer = $customerId === null ? null : Partner::query()->find($customerId);
        if ($customer === null) {
            return;
        }

        $today ??= CarbonImmutable::now();
        DB::transaction(function () use ($customer, $today): void {
            foreach (ReceivableBasis::cases() as $basis) {
                $this->refreshBasis($customer, $basis, $today);
            }
        });
    }

    private function refreshBasis(Partner $customer, ReceivableBasis $basis, CarbonImmutable $today): void
    {
        $existing = ReceivableMonth::query()->ofBasis($basis)->where('customer_id', $customer->id)->lockForUpdate()->get()->keyBy('year_month');
        $adjustments = $existing->filter(fn (ReceivableMonth $month): bool => $month->tax_adjustment !== 0)
            ->map(fn (ReceivableMonth $month): int => $month->tax_adjustment)
            ->all();

        $closing = $customer->closing_day;
        $until = $basis === ReceivableBasis::Cycle ? BillingCycle::monthFor($today, $closing) : YearMonth::fromDate($today);
        $months = ReceivableRoll::months(
            $this->sales($customer->id, $basis),
            $this->receipts($customer->id, $basis),
            $adjustments,
            RoundingMode::fromLabel($customer->rounding?->value),
            $until,
        );

        foreach ($months as $key => $figures) {
            $values = [
                ...$figures,
                'month_start' => YearMonth::parse($key)->firstDay()->toDateString(),
                'closing_day' => $basis === ReceivableBasis::Cycle ? $closing?->value : null,
            ];
            $month = $existing->get($key);
            if ($month === null) {
                (new ReceivableMonth(['basis' => $basis, 'customer_id' => $customer->id, 'year_month' => $key, ...$values]))->saveQuietly();
            } else {
                $month->forceFill($values);
                if ($month->isDirty()) {
                    $month->saveQuietly();
                }
            }
        }

        $stale = $existing->keys()->diff(array_keys($months))->all();
        if ($stale !== []) {
            ReceivableMonth::query()->ofBasis($basis)->where('customer_id', $customer->id)->whereIn('year_month', $stale)->delete();
        }
    }

    /** @return array<string, list<array{tax_rate: string, amount: int}>> */
    private function sales(int $customerId, ReceivableBasis $basis): array
    {
        $lines = [];
        Sale::query()->where('customer_id', $customerId)->get(['sold_on', 'billing_month', 'tax_rate', 'amount'])
            ->each(function (Sale $sale) use (&$lines, $basis): void {
                $key = $basis === ReceivableBasis::Cycle ? $sale->billing_month : YearMonth::fromDate($sale->sold_on)->key();
                $lines[$key][] = ['tax_rate' => $sale->tax_rate, 'amount' => (int) $sale->amount];
            });

        return $lines;
    }

    /** @return array<string, int> */
    private function receipts(int $customerId, ReceivableBasis $basis): array
    {
        $sums = [];
        ReceiptLine::query()
            ->join('receipts', 'receipts.id', '=', 'receipt_lines.receipt_id')
            ->where('receipts.customer_id', $customerId)
            ->whereNotNull('receipt_lines.received_on')
            ->get(['receipt_lines.received_on', 'receipt_lines.amount', 'receipt_lines.fee', 'receipt_lines.discount', 'receipts.closing_day'])
            ->each(function (ReceiptLine $line) use (&$sums, $basis): void {
                if ($line->received_on === null) {
                    return;
                }
                $key = $basis === ReceivableBasis::Cycle
                    ? BillingCycle::monthFor($line->received_on, ClosingDay::tryFrom((string) $line->getAttribute('closing_day')))->key()
                    : YearMonth::fromDate($line->received_on)->key();
                $sums[$key] = ($sums[$key] ?? 0) + $line->settled();
            });

        return $sums;
    }
}
