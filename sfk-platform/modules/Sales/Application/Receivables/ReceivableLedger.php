<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Receivables;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\MasterData\Models\Partner;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Domain\Receivables\ReceivableGap;
use Modules\Sales\Domain\Receivables\ReceivableRoll;
use Modules\Sales\Models\ReceiptLine;
use Modules\Sales\Models\ReceivableMonth;
use Modules\Sales\Models\Sale;

/**
 * Keeps 23. 売掛管理 (calendar) and 23-2. 売掛管理(締日別) (cycle) of a customer in line with its sales and receipts
 * (docs/receivables.md): the sales, receipts and hand-entered 消費税端数調整 are the source; `receivable_months` is a cache
 * of ReceivableRoll over them, rebuilt for the whole customer on every change (SalesObserver: sales, receipts, the
 * customer's 締日 / 端数区分, 消費税端数調整) and by the daily roll-forward (`sales:receivables-roll`), which also writes
 * the months nothing was saved in. Rebuilding is an idempotent upsert per (basis, customer, 年月): rows that differ are
 * updated, missing ones created, months without balance or activity removed — a second run changes nothing. Replaces
 * the legacy Customine upserts on sales save, 「再集計」 / 「一括更新」 and the month-creation buttons, and covers what they
 * missed: receipts saved in 21, deleted sales (D-06), a changed customer or date, credits (defect 1), months after a
 * corrected one (defect 3) and gaps (defect 5).
 *
 * - Period: ReceivableBasis::periodOf() — sales by 売上日 (cycle: their 月度 `billing_month`, fixed when the sale is saved
 *   by its effective closing day, BR-AR-02, so an issued invoice keeps its cycle when the master changes), receipt rows by
 *   入金日 (cycle: by the invoice's 今回締日, else — a payment before the invoice — the customer's 締日, BR-AR-06), with
 *   入金 + 手数料 + 歩引き (BR-AR-04).
 * - Tax per month and rate, rounded by the customer's current 端数区分 (BR-TX-06).
 */
final class ReceivableLedger
{
    /**
     * Rebuilds the customer's months of both bases up to the current month.
     *
     * @return array{created: int, updated: int, deleted: int}
     */
    public function refresh(?int $customerId, ?CarbonImmutable $today = null): array
    {
        $changes = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        $customer = $customerId === null ? null : Partner::query()->find($customerId);
        if ($customer === null) {
            return $changes;
        }

        $today ??= CarbonImmutable::now();
        DB::transaction(function () use ($customer, $today, &$changes): void {
            foreach (ReceivableBasis::cases() as $basis) {
                foreach ($this->refreshBasis($customer, $basis, $today) as $kind => $count) {
                    $changes[$kind] += $count;
                }
            }
        });

        return $changes;
    }

    /**
     * Roll-forward of every customer with sales, receipts or receivable months (the month close, daily by the scheduler):
     * all their missing months from the first one up to the current month are written, stale ones corrected.
     *
     * @param  list<int>|null  $customerIds  null = all
     * @return array{customers: int, created: int, updated: int, deleted: int}
     */
    public function rollForward(?array $customerIds = null, ?CarbonImmutable $today = null): array
    {
        $ids = $customerIds ?? DB::table('sales')->whereNotNull('customer_id')->distinct()->pluck('customer_id')
            ->merge(DB::table('receipts')->whereNotNull('customer_id')->distinct()->pluck('customer_id'))
            ->merge(DB::table('receivable_months')->distinct()->pluck('customer_id'))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()->sort()->values()->all();

        $totals = ['customers' => count($ids), 'created' => 0, 'updated' => 0, 'deleted' => 0];
        foreach ($ids as $id) {
            foreach ($this->refresh($id, $today) as $kind => $count) {
                $totals[$kind] += $count;
            }
        }

        return $totals;
    }

    /**
     * 前月売掛残 of the customer's `$period` from the stored months — the one function every screen, batch and report
     * asks (invoices, 請求書更新), by ReceivableRoll::opening().
     *
     * @throws ReceivableGap when the month before is missing although the latest earlier one did not close at 0
     */
    public function openingBalance(int $customerId, ReceivableBasis $basis, YearMonth $period): int
    {
        $latest = ReceivableMonth::query()->ofBasis($basis)->where('customer_id', $customerId)
            ->where('year_month', '<', $period->key())
            ->orderByDesc('year_month')
            ->first(['year_month', 'closing_balance']);

        return ReceivableRoll::opening($latest === null ? [] : [$latest->year_month => $latest->closing_balance], $period, $customerId);
    }

    /** @return array{created: int, updated: int, deleted: int} */
    private function refreshBasis(Partner $customer, ReceivableBasis $basis, CarbonImmutable $today): array
    {
        $changes = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        $existing = ReceivableMonth::query()->ofBasis($basis)->where('customer_id', $customer->id)->lockForUpdate()->get()->keyBy('year_month');
        $adjustments = $existing->filter(fn (ReceivableMonth $month): bool => $month->tax_adjustment !== 0)
            ->map(fn (ReceivableMonth $month): int => $month->tax_adjustment)
            ->all();

        $closing = $customer->closing_day;
        $months = ReceivableRoll::months(
            $this->sales($customer->id, $basis),
            $this->receipts($customer->id, $basis, $closing),
            $adjustments,
            RoundingMode::fromLabel($customer->rounding?->value),
            $basis->periodOf($today, $closing),
            $customer->id,
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
                $changes['created']++;
            } else {
                $month->forceFill($values);
                if ($month->isDirty()) {
                    $month->saveQuietly();
                    $changes['updated']++;
                }
            }
        }

        $stale = $existing->keys()->diff(array_keys($months))->all();
        if ($stale !== []) {
            $changes['deleted'] = ReceivableMonth::query()->ofBasis($basis)->where('customer_id', $customer->id)->whereIn('year_month', $stale)->delete();
        }

        return $changes;
    }

    /** @return array<string, list<array{tax_rate: string, amount: int}>> */
    private function sales(int $customerId, ReceivableBasis $basis): array
    {
        $lines = [];
        Sale::query()->where('customer_id', $customerId)->get(['sold_on', 'billing_month', 'tax_rate', 'amount'])
            ->each(function (Sale $sale) use (&$lines, $basis): void {
                $key = $basis === ReceivableBasis::Cycle ? $sale->billing_month : $basis->periodOf($sale->sold_on, null)->key();
                $lines[$key][] = ['tax_rate' => $sale->tax_rate, 'amount' => (int) $sale->amount];
            });

        return $lines;
    }

    /** @return array<string, int> */
    private function receipts(int $customerId, ReceivableBasis $basis, ?ClosingDay $customerClosing): array
    {
        $sums = [];
        ReceiptLine::query()
            ->join('receipts', 'receipts.id', '=', 'receipt_lines.receipt_id')
            ->where('receipts.customer_id', $customerId)
            ->whereNotNull('receipt_lines.received_on')
            ->get(['receipt_lines.received_on', 'receipt_lines.amount', 'receipt_lines.fee', 'receipt_lines.discount', 'receipts.closing_day'])
            ->each(function (ReceiptLine $line) use (&$sums, $basis, $customerClosing): void {
                if ($line->received_on === null) {
                    return;
                }
                $closing = ClosingDay::tryFrom((string) $line->getAttribute('closing_day')) ?? $customerClosing;
                $key = $basis->periodOf($line->received_on, $closing)->key();
                $sums[$key] = ($sums[$key] ?? 0) + $line->settled();
            });

        return $sums;
    }
}
