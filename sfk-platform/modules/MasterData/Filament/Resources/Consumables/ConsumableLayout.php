<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Consumables;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Modules\Core\Filament\Records\RecordLayout;
use Modules\MasterData\Domain\Consumables\ConsumableFields;

/**
 * The legacy form of 19. 消耗品マスタ, row by row with the field widths of the app template (SYSTEM_BRAIN APP_19
 * fields.md; widths from the template ZIP). The form and the record page both build from it.
 */
final class ConsumableLayout
{
    /** Rows: [attribute, width px]. */
    private const ROWS = [
        [['name', 579], ['box_type', 196], ['kind', 196], ['reorder_point', 193]],
        [['amount', 193], ['unit_price', 193], ['unit', 193], ['stock', 193], ['lot_size', 193], ['supplier_id', 501]],
        [['notes', 579]],
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
                ->schema(self::rows(self::DEVELOPER, $make))
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
     * @param  list<list<array{0: string, 1: int}>>  $rows
     * @param  callable(string): Component  $make
     */
    private static function section(array $rows, callable $make): Section
    {
        return Section::make()->schema(self::rows($rows, $make))->extraAttributes(['class' => 'kt-record'])->columnSpanFull();
    }

    /**
     * @param  list<list<array{0: string, 1: int}>>  $rows
     * @param  callable(string): Component  $make
     * @return list<Component>
     */
    private static function rows(array $rows, callable $make): array
    {
        return array_map(
            fn (array $row): Component => RecordLayout::row(array_map(fn (array $cell): array => [$make($cell[0]), $cell[1]], $row)),
            $rows,
        );
    }
}
