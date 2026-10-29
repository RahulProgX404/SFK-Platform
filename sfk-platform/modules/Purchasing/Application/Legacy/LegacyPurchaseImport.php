<?php

declare(strict_types=1);

namespace Modules\Purchasing\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\MasterData\Domain\Partners\PurchaseCategory;
use Modules\Purchasing\Application\Purchases\PurchaseOrderTotals;
use Modules\Purchasing\Models\Purchase;

/**
 * Takes over 05. 納品(仕入)入力 (legacy app 10, SYSTEM_BRAIN APP_05, BP-05): 納品(仕入)ID and 納品(仕入)入力No kept,
 * 納品(仕入)日, 仕入先 (by its 得意先ID, else name; a purchase without one is skipped), 仕入カテゴリ, the job by its 受注No,
 * 品名 / 数量 / 単価, 金額 and the cost components (断裁 / 版代 / 置き版代 / 特色), FSC, 帳端処理, 仕入原価超過 and 通知先.
 * 仕入金額合計 is derived (BR-PU-03; a difference from the stored one is reported).
 *
 * Afterwards the jobs add up their purchases again (PurchaseOrderTotals: 仕入金額 / 仕入断裁金額 / 仕入版代 …); the
 * payables follow with 23. 買掛管理 (after 07. 支払入力). The 納品(仕入)入力No sequence continues after the highest number.
 */
final class LegacyPurchaseImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
        private readonly LegacyPartners $partners,
        private readonly DocumentNumberGenerator $numbers,
        private readonly PurchaseOrderTotals $orderTotals,
    ) {}

    public function legacyAppId(): string
    {
        return '10';
    }

    public function label(): string
    {
        return '05. 納品(仕入)入力';
    }

    public function step(): int
    {
        return 100;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $orders = DB::table('orders')->pluck('id', 'order_number')->all();
        $touched = [];
        $batch = 0;

        DB::beginTransaction();
        try {
            foreach ($export->records() as $record) {
                $orderId = $this->importRecord($record, $orders, $log);
                if ($orderId !== null) {
                    $touched[$orderId] = true;
                }
                if (++$batch % 500 === 0) {
                    DB::commit();
                    DB::beginTransaction();
                }
            }

            $this->rows->syncIdSequence('purchases');
            $this->continueNumbers();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $this->refreshJobs(array_keys($touched), $log);
    }

    /**
     * @param  array<string, int>  $orders  注番 => id
     * @return int|null the job of the purchase
     */
    private function importRecord(LegacyRecord $record, array $orders, LegacyImportLog $log): ?int
    {
        $label = '納品(仕入)ID '.$record->id();
        $supplierId = $this->partners->find($record->int('仕入先_得意先ID'), $record->exactText('仕入先'));
        if ($supplierId === null) {
            $log->skipped("{$label}: 仕入先「{$record->text('仕入先')}」が見つかりません");

            return null;
        }

        $number = $record->text('受注No');
        $orderId = $number !== null ? ($orders[$number] ?? null) : null;
        if ($number !== null && $orderId === null) {
            $log->warn("{$label}: 受注No「{$number}」の受注が見つかりません");
        }

        $category = $record->text('仕入カテゴリ');
        if ($category !== null && PurchaseCategory::tryFrom($category) === null) {
            $log->warn("{$label}: 仕入カテゴリ「{$category}」は選択肢にないため空欄にしました");
        }

        $purchaseNumber = $record->text('納品_仕入_入力No');
        if ($purchaseNumber !== null && DB::table('purchases')->where('purchase_number', $purchaseNumber)->where(fn ($query) => $query->whereNull('legacy_id')->orWhere('legacy_id', '!=', $record->id()))->exists()) {
            $log->warn("{$label}: 納品(仕入)入力No「{$purchaseNumber}」は他の納品(仕入)にあるため空欄にしました");
            $purchaseNumber = null;
        }

        $amounts = [
            'amount' => $record->int('金額_0') ?? 0,
            'cutting_amount' => $record->int('断裁金額') ?? 0,
            'plate_amount' => $record->int('版代') ?? 0,
            'stored_plate_amount' => $record->int('置き版代') ?? 0,
            'spot_color_amount' => $record->int('特色') ?? 0,
        ];
        $total = array_sum($amounts);
        $stored = $record->int('仕入金額合計');
        if ($stored !== null && $stored !== $total) {
            $log->warn("{$label}: 仕入金額合計 {$stored} は再計算で {$total} になりました");
        }

        $updatedBy = $this->users->id($record->modifier());
        [$id, $created] = $this->rows->save('purchases', $record->id(), [
            'purchase_number' => $purchaseNumber,
            'purchased_on' => $record->date('納品_仕入_日') ?? substr((string) $record->createdAt(), 0, 10),
            'supplier_id' => $supplierId,
            'category' => PurchaseCategory::tryFrom((string) $category)?->value,
            'order_id' => $orderId,
            'product_name' => $record->text('品名_0'),
            'quantity' => $record->decimal('数量_0'),
            'unit_price' => $record->decimal('単価_0'),
            ...$amounts,
            'total' => $total,
            'is_fsc' => $record->checked('FSC'),
            'period_end' => $record->checked('帳端処理'),
            'overruns' => $record->choices('仕入原価超過') !== [] ? json_encode($record->choices('仕入原価超過'), JSON_UNESCAPED_UNICODE) : null,
            'created_by' => $this->users->id($record->creator()),
            'updated_by' => $updatedBy,
            'created_at' => $record->createdAt(),
            'updated_at' => $record->updatedAt(),
        ]);

        $purchase = Purchase::query()->findOrFail($id);
        $purchase->notifiedUsers()->sync(array_values(array_filter(array_map(fn (array $user): ?int => $this->users->id($user), $record->users('通知先')))));
        if ($purchaseNumber === null) {
            $purchase->assignNumber();
            $purchase->saveQuietly();
            $log->warn("{$label}: 納品(仕入)入力No がないため {$purchase->purchase_number} を付けました");
        }

        if ($created) {
            $this->rows->startHistory($purchase, $updatedBy, $record->updatedAt());
            $log->created();
        } else {
            $log->updated();
        }

        return $orderId;
    }

    /** 納品(仕入)入力No: new purchases continue after the highest number taken over (BR-PU-01). */
    private function continueNumbers(): void
    {
        $highest = 0;
        foreach (DB::table('purchases')->pluck('purchase_number') as $number) {
            if (is_string($number) && preg_match('/^\d+$/', $number) === 1) {
                $highest = max($highest, (int) $number);
            }
        }

        if ($highest > 0) {
            $this->numbers->ensureNextAtLeast(Purchase::NUMBER_SEQUENCE, $highest + 1);
        }
    }

    /**
     * The jobs add up their purchases again; a 仕入金額 different from the one 04 stored is reported.
     *
     * @param  list<int>  $orderIds
     */
    private function refreshJobs(array $orderIds, LegacyImportLog $log): void
    {
        foreach (array_chunk($orderIds, 300) as $chunk) {
            DB::transaction(function () use ($chunk, $log): void {
                $before = DB::table('orders')->whereIn('id', $chunk)->pluck('purchase_amount', 'id')->all();
                foreach ($chunk as $orderId) {
                    $this->orderTotals->refresh($orderId);
                }
                foreach (DB::table('orders')->whereIn('id', $chunk)->get(['id', 'order_number', 'purchase_amount']) as $order) {
                    $stored = (int) ($before[$order->id] ?? 0);
                    $derived = (int) $order->purchase_amount;
                    if ($stored !== $derived) {
                        $log->warn("注番 {$order->order_number}: 仕入金額 {$stored} は納品(仕入)の合計で {$derived} になりました");
                    }
                }
            });
        }
    }
}
