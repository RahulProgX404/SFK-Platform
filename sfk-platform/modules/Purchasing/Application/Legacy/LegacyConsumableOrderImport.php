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
use Modules\Purchasing\Domain\ConsumableOrders\ConsumableOrderStatus;
use Modules\Purchasing\Models\ConsumableOrder;

/**
 * Takes over 20. 消耗品発注 (legacy app 31, SYSTEM_BRAIN APP_20): レコード番号 kept, 発注日, 発注先 (by its 得意先ID),
 * ステータス (process management: 未処理 / 確認中 / 承認済), 発注者 and the 発注明細 rows with their 消耗品 (19 by 商品名).
 */
final class LegacyConsumableOrderImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
        private readonly LegacyPartners $partners,
    ) {}

    public function legacyAppId(): string
    {
        return '31';
    }

    public function label(): string
    {
        return '20. 消耗品発注';
    }

    public function step(): int
    {
        return 120;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $consumables = DB::table('consumables')->pluck('id', 'name')->all();

        DB::transaction(function () use ($export, $log, $consumables): void {
            foreach ($export->records() as $record) {
                $label = 'レコード番号 '.$record->id();
                $status = $record->text('ステータス');
                if ($status !== null && ConsumableOrderStatus::tryFrom($status) === null) {
                    $log->warn("{$label}: ステータス「{$status}」は未処理にしました");
                }

                $updatedBy = $this->users->id($record->modifier());
                [$id, $created] = $this->rows->save('consumable_orders', $record->id(), [
                    'ordered_on' => $record->date('発注日') ?? substr((string) $record->createdAt(), 0, 10),
                    'supplier_id' => $this->partners->find($record->int('発注先レコード番号'), $record->exactText('発注先')),
                    'status' => (ConsumableOrderStatus::tryFrom((string) $status) ?? ConsumableOrderStatus::Unprocessed)->value,
                    'notes' => $record->text('備考_0'),
                    'created_by' => $this->users->id($record->creator()),
                    'updated_by' => $updatedBy,
                    'created_at' => $record->createdAt(),
                    'updated_at' => $record->updatedAt(),
                ]);

                $this->rows->replaceRows('consumable_order_lines', 'consumable_order_id', $id, $this->lines($record, $consumables, $label, $log));
                $order = ConsumableOrder::query()->findOrFail($id);
                $order->orderers()->sync(array_values(array_filter(array_map(fn (array $user): ?int => $this->users->id($user), $record->users('発注者')))));

                if ($created) {
                    $this->rows->startHistory($order, $updatedBy, $record->updatedAt());
                    $log->created();
                } else {
                    $log->updated();
                }
            }

            $this->rows->syncIdSequence('consumable_orders');
        });
    }

    /**
     * @param  array<string, int>  $consumables  名称 => id
     * @return list<array<string, mixed>>
     */
    private function lines(LegacyRecord $record, array $consumables, string $label, LegacyImportLog $log): array
    {
        $lines = [];
        foreach ($record->table('発注明細') as $row) {
            if ($row->isBlank('商品名', '数量', '単価')) {
                continue;
            }

            $name = $row->text('商品名');
            $consumableId = $name !== null ? ($consumables[$name] ?? null) : null;
            if ($name !== null && $consumableId === null) {
                $log->warn("{$label}: 商品名「{$name}」が19. 消耗品マスタにありません");
            }

            $lines[] = [
                'sort' => count($lines),
                'consumable_id' => $consumableId,
                'unit_price' => $row->decimal('単価'),
                'quantity' => $row->decimal('数量'),
                'unit' => $row->text('単位'),
                'requested_on' => $row->date('希望納期'),
                'orderer' => $row->text('発注者名又は番号'),
                'delivered_on' => $row->date('納品日'),
                'notes' => $row->text('備考'),
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $lines;
    }
}
