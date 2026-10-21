<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Consumables;

/**
 * Field labels of 19. 消耗品マスタ exactly as in the legacy system (SYSTEM_BRAIN APP_19 fields.md), by attribute, and the
 * reorder signal 発注判定.
 */
final class ConsumableFields
{
    public const LABELS = [
        'name' => '名称',
        'box_type' => 'カテゴリ',
        'kind' => '区分',
        'reorder_point' => '発注点',
        'amount' => '金額',
        'unit_price' => '単価',
        'unit' => '単位',
        'stock' => '在庫数',
        'lot_size' => 'ロット数',
        'supplier_id' => '仕入先名',
        'notes' => '備考',
        'id' => 'レコード番号',
        'creator' => '作成者',
        'created_at' => '作成日時',
        'updater' => '更新者',
        'updated_at' => '更新日時',
        'reorder_flag' => '発注判定',
        'supplier_code' => '得意先コード',
        'purchase_category' => '仕入カテゴリ',
        'legacy_id' => '旧システムレコード番号',
    ];

    public const REORDER = '要発注';

    public static function label(string $attribute): string
    {
        return self::LABELS[$attribute];
    }

    /**
     * 発注判定: 「要発注」 when 在庫数 − 発注点 ≤ 0, blank while either is blank (legacy formula
     * IF(AND(在庫数<>"",発注点<>""),IF(在庫数-発注点<=0,"要発注",""),"")).
     */
    public static function reorderFlag(int|float|string|null $stock, int|float|string|null $reorderPoint): string
    {
        if ($stock === null || $stock === '' || $reorderPoint === null || $reorderPoint === '') {
            return '';
        }

        return (float) $stock - (float) $reorderPoint <= 0 ? self::REORDER : '';
    }
}
