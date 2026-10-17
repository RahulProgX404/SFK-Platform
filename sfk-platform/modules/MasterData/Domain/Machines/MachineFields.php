<?php

declare(strict_types=1);

namespace Modules\MasterData\Domain\Machines;

/**
 * Field labels of 03. 機械マスタ exactly as in the legacy system (SYSTEM_BRAIN APP_03 fields.md), by attribute. Used by
 * the screens so the names match what staff know.
 */
final class MachineFields
{
    public const LABELS = [
        'code' => '機械コード',
        'name' => '機械名',
        'processing_rate' => '加工単価(枚)',
        'changeover_rate' => '型替単価(回)',
        'handwork_rate' => '内職単価(H)',
        'machine_rate' => '機械単価(H)',
        'printing_rate' => '印刷単価',
        'plate_change_rate' => '版替単価',
        'ink_mixing_rate' => '練インキ単価',
        'notes' => '備考',
        'id' => '機械マスタID',
        'creator' => '作成者',
        'created_at' => '作成日時',
        'updater' => '更新者',
        'updated_at' => '更新日時',
        'legacy_id' => '旧システム機械マスタID',
    ];

    /** The seven rate types (APP_03 "MUST be preserved"), in the legacy form order. */
    public const RATES = [
        'processing_rate', 'changeover_rate', 'handwork_rate', 'machine_rate', 'printing_rate', 'plate_change_rate', 'ink_mixing_rate',
    ];

    public static function label(string $attribute): string
    {
        return self::LABELS[$attribute];
    }
}
