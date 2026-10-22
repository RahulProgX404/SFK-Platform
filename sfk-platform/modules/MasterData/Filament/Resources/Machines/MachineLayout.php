<?php

declare(strict_types=1);

namespace Modules\MasterData\Filament\Resources\Machines;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Modules\Core\Filament\Records\RecordSections;
use Modules\MasterData\Domain\Machines\MachineFields;

/**
 * The form of 03. 機械マスタ (fields of SYSTEM_BRAIN APP_03 fields.md), grouped on a 12-column grid of the 1,500 px
 * canvas: 基本情報 (機械コード, 機械名), 単価 and その他. The form and the record page both build from it.
 */
final class MachineLayout
{
    /** Rows: [attribute, width px]. */
    private const ROWS = [
        '基本情報',
        [['code', 250], ['name', 750]],
        '単価',
        [['processing_rate', 375], ['changeover_rate', 375], ['handwork_rate', 375], ['machine_rate', 375]],
        [['printing_rate', 375], ['plate_change_rate', 375], ['ink_mixing_rate', 375]],
        'その他',
        [['notes', 1500]],
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
                ->schema(RecordSections::rows(self::DEVELOPER, $make))
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
     * @param  list<list<array{0: string|null, 1: int}>|string>  $rows  rows, a string row is a group label
     * @param  callable(string): ?Component  $make
     */
    private static function section(array $rows, callable $make): Section
    {
        return Section::make()->schema(RecordSections::rows($rows, $make))->extraAttributes(['class' => 'kt-record'])->columnSpanFull();
    }
}
