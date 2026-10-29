<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Sales\Application\Receivables\ReceivableLedger;
use Modules\Sales\Domain\Receivables\ReceivableBasis;
use Modules\Sales\Models\ReceivableMonth;

/**
 * 23. 売掛管理 (calendar) and 23-2. 売掛管理(締日別) (cycle) — SYSTEM_BRAIN APP_23 / APP_23-2, BP-07/08: the receivables
 * are not copied, they are derived from the sales (06) and receipts (21) taken over before (ReceivableLedger). Only
 * 消費税端数調整 (entered by hand) is taken over. Then every customer's months are recalculated and compared with the
 * legacy ones: a 当月売掛残 that differs is reported (reconciliation, roadmap Phase 5) — e.g. a balance carried from
 * before the legacy system, which the new ledger does not hold.
 */
abstract class LegacyReceivables implements LegacyImporter
{
    public function __construct(
        private readonly LegacyPartners $partners,
        private readonly ReceivableLedger $ledger,
    ) {}

    abstract protected function basis(): ReceivableBasis;

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        /** @var array<int, array<string, array{balance: int|null, label: string}>> $legacy customer => month => 当月売掛残 */
        $legacy = [];

        DB::transaction(function () use ($export, $log, &$legacy): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $customerId = $this->partners->find($record->int('得意先ID'), $record->exactText('得意先名'));
                $date = $record->date('年月日');
                if ($customerId === null || $date === null) {
                    $log->skipped("{$label}: 得意先「{$record->text('得意先名')}」または年月がありません");

                    continue;
                }

                $month = substr($date, 0, 7);
                $legacy[$customerId][$month] = ['balance' => $record->int('当月売掛残'), 'label' => $record->text('得意先名').' '.$month];

                // 消費税端数調整 (entered by hand): kept by the ledger on recalculation
                $adjustment = $record->int('消費税端数調整') ?? 0;
                $existing = ReceivableMonth::query()->where('basis', $this->basis()->value)->where('customer_id', $customerId)->where('year_month', $month)->first();
                if ($existing !== null) {
                    $existing->forceFill(['tax_adjustment' => $adjustment])->saveQuietly();
                    $log->updated();
                } else {
                    if ($adjustment !== 0) {
                        (new ReceivableMonth)->forceFill([
                            'basis' => $this->basis()->value, 'customer_id' => $customerId, 'year_month' => $month, 'month_start' => $month.'-01', 'tax_adjustment' => $adjustment,
                        ])->saveQuietly();
                    }
                    $log->created();
                }
            }
        });

        // every customer with sales or receipts, and those of the legacy ledger (both bases are recalculated)
        $customers = DB::table('sales')->distinct()->pluck('customer_id')
            ->merge(DB::table('receipts')->distinct()->pluck('customer_id'))
            ->merge(array_keys($legacy))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();
        foreach ($customers as $customerId) {
            $this->ledger->refresh($customerId);
        }

        foreach ($legacy as $customerId => $months) {
            $derived = ReceivableMonth::query()->where('basis', $this->basis()->value)->where('customer_id', $customerId)->pluck('closing_balance', 'year_month')->all();
            ksort($months);
            foreach ($months as $month => $legacyMonth) {
                $balance = (int) ($derived[$month] ?? 0);
                if ($legacyMonth['balance'] !== null && $legacyMonth['balance'] !== $balance) {
                    $log->warn("{$legacyMonth['label']}: 当月売掛残 {$legacyMonth['balance']} は再計算で {$balance} になりました");
                }
            }
        }
    }
}
