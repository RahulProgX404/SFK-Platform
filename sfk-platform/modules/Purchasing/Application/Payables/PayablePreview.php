<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Payables;

use Carbon\CarbonImmutable;
use Modules\Core\Domain\Calendar\YearMonth;
use Modules\MasterData\Models\Partner;
use Modules\Purchasing\Domain\Payables\PayableCategories;
use Modules\Purchasing\Models\PayableMonth;

/**
 * 「(新) 前月未作成」 of 23_AccountPayableManagement.bundle.js (23. 買掛管理, administrators): what 前月未作成一括作成 would
 * write for a chosen month — per supplier with a ledger, the month as PayableLedger calculates it against the stored
 * record — shown only, nothing saved (the legacy 「計算結果（テスト表示）」). Unlike the legacy preview every figure is
 * known (the legacy left the formula fields of the platform blank).
 */
final class PayablePreview
{
    public const CREATE = '新規作成（繰越）';

    public const CREATE_PAYMENT_ONLY = '新規作成（支払のみ）';

    public const UPDATE_PAYMENT = '更新（支払反映）';

    public const UPDATE = '更新（再集計）';

    public const DELETE = '削除';

    /** The figures shown, in order. */
    public const FIGURES = ['previous_balance', 'paid', 'net_balance', 'purchase_amount', 'tax_amount', 'month_total', 'closing_balance'];

    public function __construct(private readonly PayableLedger $ledger) {}

    /**
     * @return list<array{action: string, supplier: string, categories: string, figures: array<string, int>}> the suppliers whose month would change
     */
    public function forMonth(YearMonth $month, ?CarbonImmutable $today = null): array
    {
        $key = $month->key();
        $stored = PayableMonth::query()->where('year_month', $key)->get()->keyBy('supplier_id');
        $rows = [];

        $suppliers = Partner::query()->whereIn('id', PayableMonth::query()->select('supplier_id')->distinct())->orderBy('id')->get();
        foreach ($suppliers as $supplier) {
            $figures = $this->ledger->calculate($supplier, $today)[$key] ?? null;
            $record = $stored->get($supplier->id);
            $action = $this->action($figures, $record);
            if ($action === null) {
                continue;
            }

            $shown = $figures ?? array_map(fn (string $name): int => (int) $record?->getAttribute($name), array_combine(self::FIGURES, self::FIGURES));
            $rows[] = [
                'action' => $action,
                'supplier' => $supplier->name,
                'categories' => implode('、', array_values(array_filter(
                    PayableCategories::LABELS,
                    fn (string $column): bool => ($shown[$column] ?? 0) !== 0,
                    ARRAY_FILTER_USE_KEY,
                ))),
                'figures' => array_map(fn (string $name): int => (int) ($shown[$name] ?? 0), array_combine(self::FIGURES, self::FIGURES)),
            ];
        }

        return $rows;
    }

    /** @param  array<string, int>|null  $figures */
    private function action(?array $figures, ?PayableMonth $record): ?string
    {
        if ($figures === null) {
            return $record === null ? null : self::DELETE;
        }
        if ($record === null) {
            return $figures['previous_balance'] === 0 && $figures['purchase_amount'] === 0 && $figures['paid'] !== 0 ? self::CREATE_PAYMENT_ONLY : self::CREATE;
        }

        foreach ($figures as $name => $value) {
            if ((int) $record->getAttribute($name) !== $value) {
                return $record->paid !== $figures['paid'] ? self::UPDATE_PAYMENT : self::UPDATE;
            }
        }

        return null;
    }
}
