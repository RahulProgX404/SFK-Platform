<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Records;

use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Text;
use Modules\Core\Access\FieldAccess;
use Modules\Core\Domain\Apps\FieldAccessLevel;

/**
 * Builds the white record cards of an app's legacy form from rows of [attribute, width px] (RecordLayout::row()):
 * `$make` returns the input or entry of an attribute, or null for what a screen does not show (that cell is left out,
 * and a row without any shown cell too); a null attribute is a legacy SPACER. A string row is a group label (blue bar)
 * over the rows after it, left out when none of them is shown. The form and the record page of an app both build from
 * the same rows.
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
        $levels = FieldAccess::onCurrentPage();
        // group labels wait for the first shown row under them: a group with nothing shown has no label (labels
        // straight after one another, e.g. two notes, belong together)
        $labels = [];
        $skipped = false;
        foreach ($rows as $row) {
            if (is_string($row)) {
                if ($skipped) {
                    $labels = [];
                    $skipped = false;
                }
                $labels[] = Text::make($row)->extraAttributes(['class' => 'kt-record-label'])->columnSpanFull();

                continue;
            }

            $cells = [];
            $shown = false;
            foreach ($row as [$attribute, $width]) {
                $component = $attribute === null ? null : self::gate($attribute, $make($attribute), $levels);
                if ($attribute !== null && $component === null) {
                    continue;
                }
                $shown = $shown || $component !== null;
                $cells[] = [$component, $width];
            }

            if (! $shown) {
                $skipped = $labels !== [];

                continue;
            }

            array_push($built, ...$labels);
            $labels = [];
            $skipped = false;
            $built[] = RecordLayout::row($cells);
        }

        return $built;
    }

    /**
     * フィールドのアクセス権 of the field on this screen (FieldAccess::onCurrentPage()): なし leaves it out, 閲覧 shows its
     * input disabled (a disabled input is not saved).
     *
     * @param  array<string, FieldAccessLevel>  $levels
     */
    public static function gate(string $attribute, ?Component $component, array $levels): ?Component
    {
        $level = $levels[$attribute] ?? FieldAccessLevel::Edit;

        if ($component === null || ! $level->canView()) {
            return null;
        }

        return ! $level->canEdit() && $component instanceof Field ? $component->disabled() : $component;
    }
}
