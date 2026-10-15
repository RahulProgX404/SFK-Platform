<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Records;

use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use InvalidArgumentException;

/**
 * Rebuilds a legacy form layout: each legacy row becomes a 12-column grid row, and each field keeps its legacy
 * width (pixels from the app template, see SYSTEM_BRAIN 03_APPS/<app>/schema.json and the template ZIP). Rows are
 * scaled to a 1,500 px legacy canvas; on small screens fields stack. Tabs sit above the fields (tabbed()) and switch
 * only the sections marked with tabPanel(); everything else stays visible.
 */
final class RecordLayout
{
    private const CANVAS_PX = 1500;

    private const COLUMNS = 12;

    /**
     * @param  list<array{0: Component|null, 1: int}>  $cells  [component (null = legacy SPACER), width in px]
     */
    public static function row(array $cells): Grid
    {
        $spans = self::spans(array_map(fn (array $cell): int => $cell[1], $cells));

        $components = [];
        foreach ($cells as $index => [$component, $px]) {
            $components[] = ($component ?? Text::make(''))
                ->columnSpan(['default' => 'full', 'md' => $spans[$index]]);
        }

        return Grid::make(['default' => 1, 'md' => self::COLUMNS])
            ->schema($components)
            ->columnSpanFull();
    }

    /**
     * @param  array<string, string>  $tabs  key (letters, digits, _) => label; the first one is selected
     * @param  list<Component>  $components  the record, incl. its tabPanel() sections
     */
    public static function tabbed(array $tabs, array $components): Group
    {
        foreach (array_keys($tabs) as $key) {
            if (preg_match('/^\w+$/', $key) !== 1) {
                throw new InvalidArgumentException("Invalid tab key [{$key}].");
            }
        }

        return Group::make([
            View::make('core::filament.record-tabs')->viewData(['tabs' => $tabs]),
            ...$components,
        ])
            ->extraAttributes(['x-data' => "{ tab: '".array_key_first($tabs)."' }", 'class' => 'kt-record-tabbed'])
            ->columnSpanFull();
    }

    /** Shows the component only while the given tab of tabbed() is selected. */
    public static function tabPanel(string $tab, Component $component, bool $selected = false): Component
    {
        if (preg_match('/^\w+$/', $tab) !== 1) {
            throw new InvalidArgumentException("Invalid tab key [{$tab}].");
        }

        return $component->extraAttributes(['x-show' => "tab === '{$tab}'", ...($selected ? [] : ['x-cloak' => true])], merge: true);
    }

    /**
     * Column spans for the given legacy widths, never more than 12 per row.
     *
     * @param  list<int>  $widths
     * @return list<int>
     */
    public static function spans(array $widths): array
    {
        $spans = array_map(fn (int $px): int => max(1, (int) round($px * self::COLUMNS / self::CANVAS_PX)), $widths);

        while (array_sum($spans) > self::COLUMNS) {
            $widest = array_search(max($spans), array_reverse($spans, preserve_keys: true), true);
            if ($spans[$widest] === 1) {
                break;
            }
            $spans[$widest]--;
        }

        return $spans;
    }
}
