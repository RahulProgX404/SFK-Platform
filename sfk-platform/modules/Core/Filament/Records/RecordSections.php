<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Records;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;

/**
 * Builds the white record cards of an app's legacy form from rows of [attribute, width px] (RecordLayout::row()):
 * `$make` returns the input or entry of an attribute, or null for what a screen does not show (that cell is left out,
 * and a row without any shown cell too); a null attribute is a legacy SPACER. A string row is a legacy label field
 * shown as a group label (blue bar). The form and the record page of an app both build from the same rows.
 */
final class RecordSections
{
    /**
     * @param  list<list<array{0: string|null, 1: int}>|string>  $rows
     * @param  callable(string): ?Component  $make
     * @param  string|null  $heading  a titled card (e.g. 開発者用フィールド), else an untitled one
     */
    public static function section(array $rows, callable $make, ?string $heading = null, bool $collapsed = false): Section
    {
        $section = Section::make($heading)
            ->schema(self::rows($rows, $make))
            ->extraAttributes(['class' => 'kt-record'])
            ->columnSpanFull();

        return $collapsed ? $section->collapsed() : $section;
    }

    /**
     * @param  list<list<array{0: string|null, 1: int}>|string>  $rows
     * @param  callable(string): ?Component  $make
     * @return list<Component>
     */
    public static function rows(array $rows, callable $make): array
    {
        $built = [];
        foreach ($rows as $row) {
            if (is_string($row)) {
                $built[] = Text::make($row)->extraAttributes(['class' => 'kt-record-label'])->columnSpanFull();

                continue;
            }

            $cells = [];
            $shown = false;
            foreach ($row as [$attribute, $width]) {
                $component = $attribute === null ? null : $make($attribute);
                if ($attribute !== null && $component === null) {
                    continue;
                }
                $shown = $shown || $component !== null;
                $cells[] = [$component, $width];
            }

            if ($shown) {
                $built[] = RecordLayout::row($cells);
            }
        }

        return $built;
    }
}
