<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Modules\Core\Filament\Records\RecordLayout;
use Modules\MasterData\Domain\Machines\MachineFields;

/**
 * The legacy form of 03. 機械マスタ, row by row with the field widths of the app template (SYSTEM_BRAIN APP_03
 * fields.md; widths from the template ZIP). The form and the record page both build from it.
 */
final class MachineLayout
{
    /** Rows: [attribute, width px]. */
    private const ROWS = [
        [['code', 193], ['name', 386]],
        [['processing_rate', 193], ['changeover_rate', 193], ['handwork_rate', 193]],
        [['machine_rate', 193], ['printing_rate', 193], ['plate_change_rate', 193], ['ink_mixing_rate', 193]],
        [['notes', 772]],
    ];

    private const FOOTER = [[['id', 120], ['creator', 136], ['created_at', 196], ['updater', 136], ['updated_at', 196]]];

    private const DEVELOPER = [[['legacy_id', 196]]];

    /**
     * @param  callable(string): Component  $make  builds the form input or record entry of an attribute
     * @param  bool  $withFooter  機械マスタID / 作成者 / 更新者 row (record page only)
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
        return MachineFields::label($attribute);
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
