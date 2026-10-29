<?php

declare(strict_types=1);

namespace Modules\Sales\Application\Legacy;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Core\Domain\Billing\BillingCycle;
use Modules\Core\Domain\Billing\ClosingDay;
use Modules\Core\Domain\Money\RoundingMode;
use Modules\Core\Domain\Tax\TaxRate;
use Modules\MasterData\Domain\Partners\DocumentRecipient;
use Modules\Sales\Application\Sales\SaleOrderTotals;
use Modules\Sales\Domain\Sales\SaleChoices;
use Modules\Sales\Models\Sale;

/**
 * Takes over 06. 売上入力 (legacy app 11, SYSTEM_BRAIN APP_06, BP-06): 売上入力ID and 売上No kept, 区分, 売上日, 売上先
 * (by its 得意先ID, else name; a sale without one is skipped), the job (by its 受注入力ID, else 注番), 品名 / 数量 /
 * 単位 / 単価 / 売上金額, 税率, the copied 締日 / 端数区分 / 送付先, 請求締日変更 + 請求日付, 摘要, 必要なもの, 受け渡し,
 * 編集, 担当者 and 按分.
 *
 * 消費税 and 月度 are derived as the app does on save (BR-TX-05, BR-AR-01/02; a 消費税 different from the stored one is
 * reported); 税区分 「税込」 does not exist any more (reported). The jobs add up their sales again afterwards
 * (SaleOrderTotals: 売上済数量 / 現在売上済金額); the receivables follow with 21 / 23. The delivery notes and invoices
 * (10 / 11) link their rows to the sales by 売上No.
 */
final class LegacySaleImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
        private readonly LegacyPartners $partners,
        private readonly SaleOrderTotals $orderTotals,
    ) {}

    public function legacyAppId(): string
    {
        return '11';
    }

    public function label(): string
    {
        return '06. 売上入力';
    }

    public function step(): int
    {
        return 150;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $lookups = [
            'orderIds' => $this->rows->ids('orders'),
            'orderNumbers' => DB::table('orders')->pluck('id', 'order_number')->all(),
        ];
        $touched = [];
        $batch = 0;

        DB::beginTransaction();
        try {
            foreach ($export->records() as $record) {
                $orderId = $this->importRecord($record, $lookups, $log);
                if ($orderId !== null) {
                    $touched[$orderId] = true;
                }
                if (++$batch % 500 === 0) {
                    DB::commit();
                    DB::beginTransaction();
                }
            }
            $this->rows->syncIdSequence('sales');
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $this->refreshJobs(array_keys($touched), $log);
    }

    /**
     * @param  array<string, array<int|string, int>>  $lookups
     * @return int|null the job of the sale
     */
    private function importRecord(LegacyRecord $record, array $lookups, LegacyImportLog $log): ?int
    {
        $label = '売上入力ID '.$record->id();
        $customerId = $this->partners->find($record->int('売上先の得意先ID'), $record->exactText('売上先'));
        if ($customerId === null) {
            $log->skipped("{$label}: 売上先「{$record->text('売上先')}」が見つかりません");

            return null;
        }

        $number = $record->text('注番');
        $orderId = $lookups['orderIds'][$record->int('受注入力ID') ?? 0] ?? ($number !== null ? ($lookups['orderNumbers'][$number] ?? null) : null);
        if ($number !== null && $orderId === null) {
            $log->warn("{$label}: 注番「{$number}」の受注が見つかりません");
        }

        $soldOn = $record->date('売上日') ?? substr((string) $record->createdAt(), 0, 10);
        $closingDay = $record->text('請求締日');
        $override = $log->choice($record->text('請求締日変更'), SaleChoices::CLOSING_DAY_OVERRIDES, $label, '請求締日変更');
        $effective = BillingCycle::effectiveClosingDay(ClosingDay::tryFrom((string) $closingDay), ClosingDay::tryFrom((string) $override));
        $rounding = $record->text('端数区分') ?? DB::table('partners')->where('id', $customerId)->value('rounding');
        $taxRate = $log->choice($record->text('税率'), array_map(fn (TaxRate $rate): string => $rate->value, TaxRate::cases()), $label, '税率') ?? TaxRate::Standard->value;
        $amount = $record->int('売上金額') ?? 0;
        $tax = Sale::taxRateOf($taxRate)->taxOn($amount, RoundingMode::fromLabel(is_string($rounding) ? $rounding : null));

        if (($stored = $record->int('消費税')) !== null && $stored !== $tax) {
            $log->warn("{$label}: 消費税 {$stored} は再計算で {$tax} になりました");
        }
        if ($record->text('税区分') === '税込') {
            $log->warn("{$label}: 税区分「税込」は新しいシステムにありません（売上金額を税抜として移行しました）");
        }
        if (($month = $record->text('月度')) !== null && preg_match('/^(\d{4})\D+(\d{1,2})/', $month, $m) === 1) {
            $legacyMonth = sprintf('%04d-%02d', (int) $m[1], (int) $m[2]);
            $billingMonth = BillingCycle::monthFor(CarbonImmutable::parse($soldOn), $effective)->key();
            if ($legacyMonth !== $billingMonth) {
                $log->warn("{$label}: 月度 {$legacyMonth} は再計算で {$billingMonth} になりました");
            }
        }

        $recipient = fn (string $code): ?string => DocumentRecipient::tryFrom((string) $record->text($code))?->value;
        $otherAddress = $record->checked('その他の送付先');
        $updatedBy = $this->users->id($record->modifier());
        [$id, $created] = $this->rows->save('sales', $record->id(), [
            'sales_number' => $record->text('売上No') ?? str_pad((string) $record->id(), Sale::NUMBER_DIGITS, '0', STR_PAD_LEFT),
            'kind' => $log->choice($record->text('区分'), SaleChoices::KINDS, $label, '区分') ?? SaleChoices::KIND_NORMAL,
            'sold_on' => $soldOn,
            'customer_id' => $customerId,
            'closing_day' => ClosingDay::tryFrom((string) $closingDay)?->value,
            'closing_day_override' => $override,
            'billing_date' => $effective === ClosingDay::AdHoc ? $record->date('請求日付') : null,
            'delivery_due_on' => $record->date('納品予定日') ?? $soldOn,
            'billing_month' => BillingCycle::monthFor(CarbonImmutable::parse($soldOn), $effective)->key(),
            'customer_rounding' => is_string($rounding) ? $rounding : null,
            'delivery_note_recipient' => $recipient('納品書送付先'),
            'invoice_recipient' => $recipient('請求書送付先'),
            'other_address' => $otherAddress,
            'manual_postal_code' => $otherAddress ? $record->text('郵便番号_手動_') : null,
            'manual_address' => $otherAddress ? $record->text('納品先住所_手動_') : null,
            'order_id' => $orderId,
            'product_name' => $record->text('品名'),
            'quantity' => $record->decimal('売上数量'),
            'unit' => $log->keptChoice($record->text('単位'), SaleChoices::UNITS, $label, '単位'),
            'unit_price' => $record->decimal('売上単価'),
            'amount' => $amount,
            'estimated_cost' => $record->int('見積原価合計'),
            'estimated_expense' => $record->int('見積経費'),
            'tax_rate' => $taxRate,
            'tax' => $tax,
            'summary' => $record->text('摘要'),
            'needs' => self::json(array_values(array_intersect($record->choices('必要なもの'), SaleChoices::NEEDS))),
            'handover' => self::json(array_values(array_intersect($record->choices('受け渡し'), SaleChoices::HANDOVERS))),
            'locked' => $record->text('編集') === '不可',
            'created_by' => $this->users->id($record->creator()),
            'updated_by' => $updatedBy,
            'created_at' => $record->createdAt(),
            'updated_at' => $record->updatedAt(),
        ]);

        $sale = Sale::query()->findOrFail($id);
        $sale->assignees()->sync(array_values(array_filter(array_map(fn (array $user): ?int => $this->users->id($user), $record->users('担当者')))));
        $this->rows->replaceRows('sale_allocations', 'sale_id', $id, $this->allocations($record));

        if ($created) {
            $this->rows->startHistory($sale, $updatedBy, $record->updatedAt());
            $log->created();
        } else {
            $log->updated();
        }

        return $orderId;
    }

    /** @return list<array<string, mixed>> 按分 rows with a 担当者 or 割合 */
    private function allocations(LegacyRecord $record): array
    {
        $rows = [];
        foreach ($record->table('按分') as $row) {
            if ($row->isBlank('担当者_按分_', '割合')) {
                continue;
            }
            $rows[] = [
                'sort' => count($rows),
                'user_id' => $this->users->id($row->user('担当者_按分_')),
                'ratio' => $row->decimal('割合'),
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $rows;
    }

    /**
     * The jobs add up their sales again; a 売上済数量 different from the one 04 stored is reported.
     *
     * @param  list<int>  $orderIds
     */
    private function refreshJobs(array $orderIds, LegacyImportLog $log): void
    {
        foreach (array_chunk($orderIds, 300) as $chunk) {
            DB::transaction(function () use ($chunk, $log): void {
                $before = DB::table('orders')->whereIn('id', $chunk)->pluck('sold_quantity', 'id')->all();
                foreach ($chunk as $orderId) {
                    $this->orderTotals->refresh($orderId);
                }
                foreach (DB::table('orders')->whereIn('id', $chunk)->get(['id', 'order_number', 'sold_quantity']) as $order) {
                    $stored = (float) ($before[$order->id] ?? 0);
                    $derived = (float) $order->sold_quantity;
                    if (abs($stored - $derived) > 0.0001) {
                        $log->warn("注番 {$order->order_number}: 売上済数量 ".self::number($stored).' は売上の合計で '.self::number($derived).' になりました');
                    }
                }
            });
        }
    }

    /** @param  list<string>  $values */
    private static function json(array $values): ?string
    {
        return $values === [] ? null : json_encode($values, JSON_UNESCAPED_UNICODE);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
