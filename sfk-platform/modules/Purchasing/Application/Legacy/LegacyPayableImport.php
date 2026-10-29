<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Purchasing\Application\Payables\PayableLedger;
use Modules\Purchasing\Models\PayableMonth;

/**
 * 23. 買掛管理 (legacy app 40, SYSTEM_BRAIN APP_23B, BP-05/08): the payables are not copied — they are derived from the
 * purchases (05) and payments (07) taken over before (PayableLedger, one month per supplier and calendar month). Only
 * what was entered by hand is taken over: 消費税端数調整. Then every supplier's months are recalculated and compared
 * with the legacy ones: a 当月買掛残 that differs is reported (reconciliation, roadmap Phase 5) — e.g. a balance carried
 * from before the legacy system, which the new ledger does not hold.
 */
final class LegacyPayableImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyPartners $partners,
        private readonly PayableLedger $ledger,
    ) {}

    public function legacyAppId(): string
    {
        return '40';
    }

    public function label(): string
    {
        return '23. 買掛管理';
    }

    public function step(): int
    {
        return 140;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        /** @var array<int, array<string, array{balance: int|null, label: string}>> $legacy supplier => month => 当月買掛残 */
        $legacy = [];

        DB::transaction(function () use ($export, $log, &$legacy): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $supplierId = $this->partners->find($record->int('得意先ID'), $record->exactText('得意先名'));
                $date = $record->date('年月日');
                if ($supplierId === null || $date === null) {
                    $log->skipped("{$label}: 仕入先「{$record->text('得意先名')}」または年月がありません");

                    continue;
                }

                $month = substr($date, 0, 7);
                $legacy[$supplierId][$month] = ['balance' => $record->int('当月買掛残'), 'label' => $record->text('得意先名').' '.$month];

                // 消費税端数調整 (entered by hand): kept by the ledger on recalculation
                $adjustment = $record->int('消費税端数調整') ?? 0;
                $existing = PayableMonth::query()->where('supplier_id', $supplierId)->where('year_month', $month)->first();
                if ($existing !== null) {
                    $existing->forceFill(['tax_adjustment' => $adjustment])->saveQuietly();
                    $log->updated();
                } elseif ($adjustment !== 0) {
                    (new PayableMonth)->forceFill(['supplier_id' => $supplierId, 'year_month' => $month, 'month_start' => $month.'-01', 'tax_adjustment' => $adjustment])->saveQuietly();
                    $log->created();
                } else {
                    $log->created();
                }
            }
        });

        // every supplier with purchases or payments, and those of the legacy ledger
        $suppliers = DB::table('purchases')->distinct()->pluck('supplier_id')
            ->merge(DB::table('payment_lines')->whereNotNull('supplier_id')->distinct()->pluck('supplier_id'))
            ->merge(array_keys($legacy))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();
        foreach ($suppliers as $supplierId) {
            $this->ledger->refresh($supplierId);
        }

        $this->reconcile($legacy, $log);
    }

    /** @param  array<int, array<string, array{balance: int|null, label: string}>>  $legacy */
    private function reconcile(array $legacy, LegacyImportLog $log): void
    {
        foreach ($legacy as $supplierId => $months) {
            $derived = PayableMonth::query()->where('supplier_id', $supplierId)->pluck('closing_balance', 'year_month')->all();
            ksort($months);
            foreach ($months as $month => $legacyMonth) {
                $balance = (int) ($derived[$month] ?? 0);
                if ($legacyMonth['balance'] !== null && $legacyMonth['balance'] !== $balance) {
                    $log->warn("{$legacyMonth['label']}: 当月買掛残 {$legacyMonth['balance']} は再計算で {$balance} になりました");
                }
            }
        }
    }
}
