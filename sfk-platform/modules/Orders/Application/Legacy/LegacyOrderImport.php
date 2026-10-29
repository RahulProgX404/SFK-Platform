<?php

declare(strict_types=1);

namespace Modules\Orders\Application\Legacy;

use Illuminate\Support\Facades\DB;
use Modules\Core\Application\Legacy\LegacyExport;
use Modules\Core\Application\Legacy\LegacyImporter;
use Modules\Core\Application\Legacy\LegacyImportLog;
use Modules\Core\Application\Legacy\LegacyPartners;
use Modules\Core\Application\Legacy\LegacyRecord;
use Modules\Core\Application\Legacy\LegacyRows;
use Modules\Core\Application\Legacy\LegacyUsers;
use Modules\Core\Application\Numbering\DocumentNumberGenerator;
use Modules\Orders\Domain\Orders\OrderChoices as C;
use Modules\Orders\Models\Order;

/**
 * Takes over 04. 受注入力 (legacy app 9, SYSTEM_BRAIN APP_04, BP-03): 受注入力ID and 注番 kept, every input field of
 * the job (OrderFields: the legacy label of each column) and its tables 用紙情報 / 印刷 / トムソン・NO・シルク・箔.
 *
 * - 取引先 by their 得意先ID where the record holds it, else by name (仕入先(刷版) / データ支給 / フィルム支給); 機械 by
 *   機械コード or name; デザイナー by name.
 * - The calculated columns (金額, 小計, 見積原価合計, 利益 …) are derived again from the inputs with the customer's
 *   端数区分 (JobCosting); differences from the stored 見積原価合計 / 売上金額 are reported. The progress and actuals
 *   (製造済数量, 売上済数量, 仕入金額 …) are taken as stored: the later apps (08, 05, 06) refresh them.
 * - Option values removed from the legacy lists later (e.g. 口糊 「HM」) stay on the old jobs (reported). Not taken over:
 *   the 作業明細表 files (not in the export — reported) and the table 売上履歴 (the sales of the job come from 06).
 * - The 注番 sequence continues after the highest 「sfk-」 number taken over; jobs saved without 注番 get the next
 *   numbers (reported).
 */
final class LegacyOrderImport implements LegacyImporter
{
    public function __construct(
        private readonly LegacyRows $rows,
        private readonly LegacyUsers $users,
        private readonly LegacyPartners $partners,
        private readonly DocumentNumberGenerator $numbers,
    ) {}

    public function legacyAppId(): string
    {
        return '9';
    }

    public function label(): string
    {
        return '04. 受注入力';
    }

    public function step(): int
    {
        return 60;
    }

    public function import(LegacyExport $export, LegacyImportLog $log): void
    {
        $machines = DB::table('machines')->get(['id', 'code', 'name']);
        $machineByCode = $machines->whereNotNull('code')->pluck('id', 'code')->all();
        $machineByName = $machines->pluck('id', 'name')->all();
        $batch = 0;
        $unnumbered = [];

        DB::beginTransaction();
        try {
            foreach ($export->records() as $record) {
                // jobs saved without 注番: numbered after all the legacy numbers are in
                if ($record->text('注番') === null) {
                    $unnumbered[] = $record;

                    continue;
                }

                $this->importRecord($record, $record->text('注番'), $log, $machineByCode, $machineByName);

                // commit every few hundred orders: a long run keeps what it has done
                if (++$batch % 300 === 0) {
                    DB::commit();
                    DB::beginTransaction();
                }
            }

            $this->rows->syncIdSequence('orders');
            $this->continueNumbers();
            foreach ($unnumbered as $record) {
                $number = DB::table('orders')->where('legacy_id', $record->id())->value('order_number') ?? $this->nextNumber();
                $log->warn("受注入力ID {$record->id()}: 注番がないため {$number} を付けました");
                $this->importRecord($record, (string) $number, $log, $machineByCode, $machineByName);
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * @param  array<string, int>  $machineByCode
     * @param  array<string, int>  $machineByName
     */
    private function importRecord(LegacyRecord $record, string $number, LegacyImportLog $log, array $machineByCode, array $machineByName): void
    {
        $label = '受注入力ID '.$record->id();
        if (DB::table('orders')->where('order_number', $number)->where(fn ($query) => $query->whereNull('legacy_id')->orWhere('legacy_id', '!=', $record->id()))->exists()) {
            $log->skipped("{$label}: 注番「{$number}」は他の受注にあります");

            return;
        }

        $customerId = $this->partners->find($record->int('受注先の得意先ID'), $record->exactText('得意先_受注先_'));
        if ($customerId === null && $record->text('得意先_受注先_') !== null) {
            $log->warn("{$label}: 得意先「{$record->text('得意先_受注先_')}」が見つかりません");
        }

        $partner = function (?int $legacyId, string $nameCode, string $field) use ($record, $label, $log): ?int {
            $name = $record->exactText($nameCode);
            $id = $this->partners->find($legacyId, $name);
            if ($id === null && trim($name) !== '') {
                $log->warn("{$label}: {$field}「".trim($name).'」が見つかりません');
            }

            return $id;
        };
        // options removed from the legacy lists later (e.g. 口糊 「HM」) stay on the old jobs: plain text columns
        $choice = fn (string $code, array $options, string $field): ?string => $log->keptChoice($record->text($code), $options, $label, $field);

        $machineCode = $record->text('機械コード');
        $machineName = $record->text('機械');
        $machineId = ($machineCode !== null ? ($machineByCode[$machineCode] ?? null) : null) ?? ($machineName !== null ? ($machineByName[$machineName] ?? null) : null);
        if ($machineId === null && ($machineCode ?? $machineName) !== null) {
            $log->warn("{$label}: 機械「".($machineName ?? $machineCode).'」が見つかりません');
        }

        $designer = $record->text('デザイナー');
        $designerId = $designer !== null ? $this->users->named($designer)?->id : null;
        if ($designer !== null && $designerId === null) {
            $log->warn("{$label}: デザイナー「{$designer}」が見つかりません");
        }

        $customerRounding = $record->text('受注先の端数区分')
            ?? ($customerId !== null ? DB::table('partners')->where('id', $customerId)->value('rounding') : null);

        $updatedBy = $this->users->id($record->modifier());
        [$id, $created] = $this->rows->save('orders', $record->id(), [
            'order_number' => $number,
            'status' => $choice('ステータス_0', C::STATUSES, 'ステータス'),
            'customer_id' => $customerId,
            'closing_day' => $record->text('締日'),
            'customer_rounding' => $customerRounding,
            'product_name' => $record->text('品名'),
            'is_fsc' => $record->checked('FSC'),
            'category1' => $choice('分類1', C::CATEGORIES1, '分類1'),
            'category2' => $choice('分類2', C::CATEGORIES2, '分類2'),
            'quantity' => $record->decimal('数量'),
            'unit' => $choice('単位', C::UNITS, '単位'),
            'sample' => $record->decimal('見本'),
            'spare' => $record->decimal('予備'),
            'due_date' => $record->date('納期'),
            'due_time' => $record->time('時間'),
            'order_date' => $record->date('受注日'),
            'expected_hours' => $record->decimal('製作完了見込み時間'),
            'manufactured_quantity' => $record->decimal('製造済数量') ?? 0,
            'sold_quantity' => $record->decimal('売上済数量') ?? 0,
            // 版下・製版・刷版
            'plate_supplier_id' => $partner(null, '仕入先_刷版_', '仕入先(刷版)'),
            'plate_version' => $choice('新旧版区分', C::PLATE_VERSIONS, '新旧版区分'),
            'plate_last_used_on' => $record->date('前回使用日'),
            'plate_count' => $record->decimal('版数'),
            'plate_unit_price' => $record->decimal('単価_刷版_'),
            'plate_storage' => $choice('保存区分', C::PLATE_STORAGES, '保存区分') ?? C::DEFAULT_PLATE_STORAGE,
            'plate_size' => $choice('版区分', C::PLATE_SIZES, '版区分'),
            'design_fee' => $record->int('デザイン料') ?? 0,
            'plate_notes' => $record->text('備考_刷版_'),
            // 製袋・製本・他加工
            'manufacturer_id' => $partner($record->int('得意先ID_製袋・製本・他加工'), '仕入先_製造場所_', '仕入先(製造場所)'),
            'bag_schedule' => $choice('製袋予定規格', C::BAG_SCHEDULES, '製袋予定規格'),
            'width' => $record->decimal('左右'),
            'height' => $record->decimal('天地'),
            'flap' => $record->decimal('ふた'),
            'bottom' => $record->decimal('底'),
            'unfolded_width' => $record->decimal('展開寸法_左右_'),
            'unfolded_height' => $record->decimal('展開寸法_天地_'),
            'envelope_shape' => $choice('角・長・洋', C::ENVELOPE_SHAPES, '角・長・洋'),
            'envelope_other' => $record->text('その他の場合'),
            'size_no' => $record->text('号'),
            'legacy_size_no' => $record->decimal('号_旧_'),
            'pasting' => $choice('貼方', C::PASTINGS, '貼方'),
            'as_per_artwork' => $record->checked('原稿通り'),
            'flap_type' => $choice('ドロップダウン_1', C::FLAP_TYPES, 'ふた区分'),
            'die_cut' => $choice('抜き', C::DIE_CUTS, '抜き'),
            'flap_glue' => $choice('口糊', C::FLAP_GLUES, '口糊'),
            'side_glue' => $choice('胴糊', C::SIDE_GLUES, '胴糊'),
            'die' => $record->text('抜型'),
            'window' => $choice('窓', C::WINDOWS, '窓'),
            'window_width' => $record->decimal('窓_左右_'),
            'window_height' => $record->decimal('窓_天地_'),
            'window_from_left' => $record->decimal('左から'),
            'window_from_bottom' => $record->decimal('底から'),
            'carton_number' => $record->text('ケース番号'),
            'per_case' => $record->decimal('入り数'),
            'per_case_specified' => $record->checked('入数指定'),
            'partition_case' => $record->checked('仕切りケース'),
            'band' => $record->checked('帯'),
            'band_sheets' => $record->decimal('帯枚数'),
            'has_label' => $record->checked('ラベル'),
            'bag_unit_price' => $record->decimal('単価_製袋'),
            'bag_manual' => $record->checked('入力方法_1'),
            'bag_manual_amount' => $record->checked('入力方法_1') ? $record->int('金額_手動__1') : null,
            'case_cost' => $record->int('ケース代'),
            'shipping_cost' => $record->int('送料'),
            'sales_amount' => $record->int('売上金額') ?? 0,
            'printed_on' => $record->date('印刷日'),
            'print_location' => $record->text('印刷場所'),
            'delivered_on' => $record->date('持込日'),
            'delivery_location' => $record->text('持込場所'),
            'delivery_service' => $record->text('持込便'),
            'completed_on' => $record->date('完成日'),
            'remarks' => $record->text('備考'),
            // その他
            'memo' => $record->text('備考_0'),
            'worker_message' => $record->text('作業者への伝言メモ'),
            'notes' => $record->text('注意事項等'),
            'internal_cost' => $record->int('社内原価') ?? 0,
            // 各種原価 (from 05, refreshed by its import)
            'purchase_amount' => $record->int('仕入金額') ?? 0,
            'purchase_cutting_amount' => $record->int('仕入断裁金額') ?? 0,
            'purchase_plate_amount' => $record->int('仕入版代') ?? 0,
            'purchase_stored_plate_amount' => $record->int('仕入置き版代') ?? 0,
            'purchase_spot_color_amount' => $record->int('仕入特色') ?? 0,
            'sold_amount' => $record->int('現在売上済金額') ?? 0,
            // 開発者用フィールド
            'previous_order_date' => $record->date('前回受注日'),
            'previous_order_number' => $record->text('前回受注No'),
            'previous_order_quantity' => $record->decimal('前回受注数'),
            'machine_id' => $machineId,
            'manufacturing_sales' => $record->int('製造売上'),
            'data_supply' => $choice('データ支給', C::SUPPLIES, 'データ支給'),
            'data_supplier_id' => $partner(null, '仕入先_データ支給_', '仕入先(データ支給)'),
            'designer_id' => $designerId,
            'film_supply' => $choice('フィルム支給', C::SUPPLIES, 'フィルム支給'),
            'film_supplier_id' => $partner(null, '仕入先_フィルム支給_', '仕入先(フィルム支給)'),
            'created_by' => $this->users->id($record->creator()),
            'updated_by' => $updatedBy,
            'created_at' => $record->createdAt(),
            'updated_at' => $record->updatedAt(),
        ]);

        $this->rows->replaceRows('order_papers', 'order_id', $id, $this->papers($record, $label, $log));
        $this->rows->replaceRows('order_printings', 'order_id', $id, $this->processes($record, '印刷', [
            'supplier' => '仕入先_印刷テーブル', 'partner' => '得意先ID_印刷テーブル', 'notes' => '備考_印刷テーブル', 'imposition' => '面付_印刷テーブル',
            'passes' => '通し数_印刷テーブル', 'spare' => '予備_印刷テーブル', 'colors' => '色数_印刷テーブル', 'inks' => '刷色_印刷テーブル',
            'price' => '単価_印刷テーブル', 'manual' => '入力方法_印刷テーブル', 'amount' => '金額_手動_',
        ], $label, $log));
        $this->rows->replaceRows('order_finishings', 'order_id', $id, $this->processes($record, 'トムソン・NO・シルク・箔', [
            'supplier' => '仕入先_トムソン・NO・シルク・箔', 'partner' => '得意先ID_トムソン・NO・シルク・箔', 'notes' => '備考_トムソン・NO・シルク・箔',
            'imposition' => '面付_トムソン・NO・シルク・箔', 'passes' => '通し数_トムソン・NO・シルク・箔', 'spare' => '予備_トムソン・NO・シルク・箔',
            'colors' => '色数_トムソン・NO・シルク・箔', 'inks' => '刷色_トムソン・NO・シルク・箔', 'price' => '単価_トムソン・NO・シルク・箔',
            'manual' => '入力方法_トムソン・NO・シルク・箔', 'amount' => '金額_手動__0',
        ], $label, $log));

        DB::table('order_assignees')->where('order_id', $id)->delete();
        $assignees = array_values(array_unique(array_filter(array_map(fn (array $user): ?int => $this->users->id($user), $record->users('担当者')))));
        if ($assignees !== []) {
            DB::table('order_assignees')->insert(array_map(fn (int $user): array => [
                'order_id' => $id, 'user_id' => $user, 'created_at' => $record->createdAt(), 'updated_at' => $record->updatedAt(),
            ], $assignees));
        }

        $order = Order::query()->findOrFail($id);
        $order->refreshTotals();
        $this->compare($record, $order, $label, $log);

        foreach (['作業明細表_注文書_' => '作業明細表(注文書)', '作業明細表_営業控_' => '作業明細表(営業控)', '作業明細表_原稿添付用_' => '作業明細表(原稿添付用)'] as $code => $field) {
            if (($files = $record->files($code)) !== []) {
                $log->warn("{$label}: {$field} は移行されません（".implode('、', $files).'）');
            }
        }

        if ($created) {
            $this->rows->startHistory($order, $updatedBy, $record->updatedAt());
            $log->created();
        } else {
            $log->updated();
        }
    }

    /** @return list<array<string, mixed>> */
    private function papers(LegacyRecord $record, string $label, LegacyImportLog $log): array
    {
        $rows = [];
        foreach ($record->table('用紙情報') as $row) {
            if ($row->isBlank('用紙名称', '仕入先_用紙_', '判', 'kg', '枚数', '用紙単価', '切', '切_旧', '束', '断裁単価', '用紙金額_手動_', '断裁金額_手動_')) {
                continue;
            }

            $supplier = $this->partners->find($row->int('得意先ID'), $row->exactText('仕入先_用紙_'));
            if ($supplier === null && $row->text('仕入先_用紙_') !== null) {
                $log->warn("{$label}: 用紙情報の仕入先「{$row->text('仕入先_用紙_')}」が見つかりません");
            }

            $rows[] = [
                'sort' => count($rows),
                'supplier_id' => $supplier,
                'paper_name' => $row->text('用紙名称'),
                'size' => $row->text('判'),
                'weight_kg' => $row->decimal('kg'),
                'grain' => $log->choice($row->text('目'), C::GRAINS, $label, '用紙情報 目'),
                'sheets' => $row->decimal('枚数'),
                'pricing' => $log->choice($row->text('計算方法'), C::PRICINGS, $label, '用紙情報 計算方法'),
                'unit_price' => $row->decimal('用紙単価'),
                'manual' => $row->checked('入力方法_用紙テーブル'),
                'manual_amount' => $row->checked('入力方法_用紙テーブル') ? $row->int('用紙金額_手動_') : null,
                'cut' => $row->text('切'),
                'bundles' => $row->decimal('束'),
                'cutting_unit_price' => $row->decimal('断裁単価'),
                'cutting_manual' => $row->checked('入力方法_用紙テーブル_断裁'),
                'cutting_manual_amount' => $row->checked('入力方法_用紙テーブル_断裁') ? $row->int('断裁金額_手動_') : null,
                'legacy_cut' => $row->decimal('切_旧'),
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $rows;
    }

    /**
     * Rows of 印刷 or トムソン・NO・シルク・箔 (same columns, field codes per table).
     *
     * @param  array<string, string>  $codes
     * @return list<array<string, mixed>>
     */
    private function processes(LegacyRecord $record, string $table, array $codes, string $label, LegacyImportLog $log): array
    {
        $rows = [];
        foreach ($record->table($table) as $row) {
            if ($row->isBlank($codes['supplier'], $codes['notes'], $codes['imposition'], $codes['passes'], $codes['spare'], $codes['colors'], $codes['inks'], $codes['price'], $codes['amount'])) {
                continue;
            }

            $supplier = $this->partners->find($row->int($codes['partner']), $row->exactText($codes['supplier']));
            if ($supplier === null && $row->text($codes['supplier']) !== null) {
                $log->warn("{$label}: {$table}の仕入先「{$row->text($codes['supplier'])}」が見つかりません");
            }

            $manual = $row->checked($codes['manual']);
            $rows[] = [
                'sort' => count($rows),
                'supplier_id' => $supplier,
                'notes' => $row->text($codes['notes']),
                'imposition' => $row->decimal($codes['imposition']),
                'passes' => $row->decimal($codes['passes']),
                'spare_passes' => $row->decimal($codes['spare']),
                'colors' => $row->decimal($codes['colors']),
                'ink_colors' => $row->text($codes['inks']),
                'unit_price' => $row->decimal($codes['price']),
                'manual' => $manual,
                'manual_amount' => $manual ? $row->int($codes['amount']) : null,
                'created_at' => $record->createdAt(),
                'updated_at' => $record->updatedAt(),
            ];
        }

        return $rows;
    }

    /** The derived totals against the stored ones (differences are expected where the legacy formulas had defects). */
    private function compare(LegacyRecord $record, Order $order, string $label, LegacyImportLog $log): void
    {
        foreach (['見積原価合計' => 'estimated_cost_total', '小計' => 'paper_subtotal'] as $code => $column) {
            $stored = $record->int($code);
            $derived = (int) $order->getAttribute($column);
            if ($stored !== null && $stored !== $derived) {
                $log->warn("{$label}: {$code} {$stored} は再計算で {$derived} になりました");
            }
        }
    }

    /** 注番: new orders continue after the highest 「sfk-」 number taken over (BR-OR-01). */
    private function continueNumbers(): void
    {
        $highest = 0;
        foreach (DB::table('orders')->where('order_number', 'like', 'sfk-%')->pluck('order_number') as $number) {
            if (preg_match('/^sfk-(\d+)$/', (string) $number, $m) === 1) {
                $highest = max($highest, (int) $m[1]);
            }
        }

        if ($highest > 0) {
            $this->numbers->ensureNextAtLeast(Order::NUMBER_SEQUENCE, $highest + 1);
        }
    }

    private function nextNumber(): string
    {
        do {
            $number = $this->numbers->next(Order::NUMBER_SEQUENCE);
        } while (DB::table('orders')->where('order_number', $number)->exists());

        return $number;
    }
}
