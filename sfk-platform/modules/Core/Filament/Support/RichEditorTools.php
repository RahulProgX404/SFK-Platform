<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Forms\Components\RichEditor\TextColor;
use Filament\Schemas\Components\View;
use Filament\Support\Icons\Heroicon;

/**
 * Tools of our rich text editors (e.g. the ポータル's お知らせ):
 * - 文字色: a palette under the button — one click colours the selection (Filament's own tool opens a second dialog
 *   with 適用). Put textColor() in the toolbar and palette() into the same form;
 * - the table tools in the toolbar (Filament's floating table bar sits outside the table and has no labels), with
 *   「表を削除」 written out, usable while the cursor is in a table.
 */
final class RichEditorTools
{
    public const TEXT_COLOR = 'sfkTextColor';

    public const TABLE_DELETE = 'sfkTableDelete';

    /** Toolbar group of the tables: insert, rows / columns, 表を削除. */
    public const TABLE_GROUP = ['table', 'tableAddRowAfter', 'tableDeleteRow', 'tableAddColumnAfter', 'tableDeleteColumn', self::TABLE_DELETE];

    /** @return list<RichEditorTool> */
    public static function tools(): array
    {
        return [
            RichEditorTool::make(self::TEXT_COLOR)
                ->label('文字色')
                ->icon(Heroicon::Swatch)
                ->activeKey('textColor')
                // the palette (palette()) opens under the button, for this editor
                ->jsHandler('$dispatch(\'sfk-text-palette\', { editor: $getEditor(), anchor: $el })'),
            RichEditorTool::make(self::TABLE_DELETE)
                ->label('表を削除')
                ->icon(Heroicon::OutlinedTrash)
                ->activeKey('table')
                ->disabledWhenNotActive()
                ->activeStyling(false)
                ->jsHandler('$getEditor()?.chain().focus().deleteTable().run()'),
        ];
    }

    /** The 文字色 palette (core::filament.text-color-palette), opened by the textColor tool. */
    public static function palette(): View
    {
        return View::make('core::filament.text-color-palette')
            ->viewData(fn (): array => ['colors' => array_map(
                fn (TextColor $color): array => ['label' => (string) $color->getLabel(), 'color' => (string) $color->getColor()],
                TextColor::getDefaults(),
            )]);
    }
}
