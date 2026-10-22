<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Modules\Core\Filament\Records\RecordSections;
use Modules\MasterData\Domain\Consumables\ConsumableFields;

/**
 * The form of 19. 消耗品マスタ (fields of SYSTEM_BRAIN APP_19 fields.md), grouped on a 12-column grid of the 1,500 px
 * canvas: 基本情報 (名称, カテゴリ, 区分, 仕入先), 単価・在庫 and その他. The form and the record page both build from it.
 */
final class ConsumableLayout
{
    /** Rows: [attribute, width px]. */
    private const ROWS = [
        '基本情報',
        [['name', 750], ['box_type', 375], ['kind', 375]],
        [['supplier_id', 750]],
        '単価・在庫',
        [['unit_price', 250], ['unit', 250], ['amount', 250], ['stock', 250], ['reorder_point', 250], ['lot_size', 250]],
        'その他',
        [['notes', 1500]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    /** 開発者用フィールド: the supplier's lookup copies, 発注判定 and the legacy id. */
    private const DEVELOPER = [[['supplier_code', 193], ['purchase_category', 193], ['reorder_flag', 193], ['legacy_id', 193]]];

    /**
     * @param  callable(string): Component  $make  builds the form input or record entry of an attribute
     * @param  bool  $withFooter  レコード番号 / 作成者 / 更新者 row (record page only)
     * @param  bool  $withDeveloperFields  開発者用フィールド (super admins only)
     * @return list<Component>
     */
    public static function components(callable $make, bool $withFooter, bool $withDeveloperFields): array
    {
        $components = [self::section(self::ROWS, $make)];

        if ($withFooter) {
            $components[] = self::section(self::FOOTER, $make);
        }

        if ($withDeveloperFields) {
            $components[] = Section::make('開発者用フィールド')
                ->collapsed()
                ->schema(RecordSections::rows(self::DEVELOPER, $make))
                ->extraAttributes(['class' => 'kt-record'])
                ->columnSpanFull();
        }

        return $components;
    }

    public static function label(string $attribute): string
    {
        return ConsumableFields::label($attribute);
    }

    /**
     * @param  list<list<array{0: string|null, 1: int}>|string>  $rows  rows, a string row is a group label
     * @param  callable(string): ?Component  $make
     */
    private static function section(array $rows, callable $make): Section
    {
        return Section::make()->schema(RecordSections::rows($rows, $make))->extraAttributes(['class' => 'kt-record'])->columnSpanFull();
    }
}
