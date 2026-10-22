<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Actions\Action;

/**
 * The legacy app buttons of a list or record page (menuActions(), from the legacy 「ボタンをメニュー位置に配置する」):
 * marked so core::filament.app-header draws them as the app's button bar — a segmented row of icon + label buttons in
 * the look of the toolbar tools (sfk-menu-btn), the buttons on the checked rows grouped together.
 */
final class MenuButtons
{
    private const MARKER = 'data-sfk-menu';

    private const COUNT_MARKER = 'data-sfk-menu-count';

    public static function mark(Action $action): Action
    {
        return $action->button()->color('gray')->extraAttributes([self::MARKER => 'true', 'class' => 'sfk-menu-btn'], merge: true);
    }

    public static function isMenuButton(mixed $action): bool
    {
        return $action instanceof Action && ($action->getExtraAttributes()[self::MARKER] ?? null) === 'true';
    }

    /** A checked-row button showing the number of checked rows as a 「N件」 badge on its corner. */
    public static function withCheckedCount(Action $action): Action
    {
        return $action->extraAttributes([self::COUNT_MARKER => 'true'], merge: true);
    }

    public static function showsCheckedCount(Action $action): bool
    {
        return ($action->getExtraAttributes()[self::COUNT_MARKER] ?? null) === 'true';
    }

    /** A button working on the checked rows of the list (AppListRecords::onCheckedRows()). */
    public static function isOnCheckedRows(Action $action): bool
    {
        return $action->hasCanAccessSelectedRecords();
    }
}
