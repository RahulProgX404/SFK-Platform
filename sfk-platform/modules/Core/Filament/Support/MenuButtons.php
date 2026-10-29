<?php

declare(strict_types=1);

namespace Modules\Core\Filament\Support;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;

/**
 * The legacy app buttons of a list or record page (menuActions(), from the legacy 「ボタンをメニュー位置に配置する」):
 * marked so core::filament.app-header draws them as the app's button bar — a segmented row of icon + label buttons in
 * the look of the toolbar tools (sfk-menu-btn), the buttons on the checked rows grouped together. A button may be a
 * menu of actions (an ActionGroup, e.g. 帳票出力 with the same ボタン名 and several サブボタン名).
 */
final class MenuButtons
{
    private const MARKER = 'data-sfk-menu';

    private const COUNT_MARKER = 'data-sfk-menu-count';

    /**
     * @template T of Action|ActionGroup
     *
     * @param  T  $action
     * @return T
     */
    public static function mark(Action|ActionGroup $action): Action|ActionGroup
    {
        $attributes = [self::MARKER => 'true', 'class' => 'sfk-menu-btn'];
        if ($action instanceof ActionGroup) {
            // a menu: ▾ after the label (core::filament.theme)
            $attributes['data-sfk-menu-group'] = 'true';
        }

        return $action->button()->color('gray')->extraAttributes($attributes, merge: true);
    }

    public static function isMenuButton(mixed $action): bool
    {
        return ($action instanceof Action || $action instanceof ActionGroup) && ($action->getExtraAttributes()[self::MARKER] ?? null) === 'true';
    }

    /** A colour for a kind of button (e.g. 納品書 'green', 請求書 'orange'; core::filament.theme): left edge, icon, tint. */
    public static function tone(Action $action, string $tone): Action
    {
        return $action->extraAttributes(['data-sfk-tone' => $tone], merge: true);
    }

    /**
     * A checked-row button showing the number of checked rows as a 「N件」 badge on its corner.
     *
     * @template T of Action|ActionGroup
     *
     * @param  T  $action
     * @return T
     */
    public static function withCheckedCount(Action|ActionGroup $action): Action|ActionGroup
    {
        return $action->extraAttributes([self::COUNT_MARKER => 'true'], merge: true);
    }

    public static function showsCheckedCount(Action|ActionGroup $action): bool
    {
        return ($action->getExtraAttributes()[self::COUNT_MARKER] ?? null) === 'true';
    }

    /** A button working on the checked rows of the list (AppListRecords::onCheckedRows()); a menu when all its actions do. */
    public static function isOnCheckedRows(Action|ActionGroup $action): bool
    {
        if ($action instanceof Action) {
            return $action->hasCanAccessSelectedRecords();
        }

        $actions = $action->getFlatActions();

        return $actions !== [] && array_all($actions, fn (Action $item): bool => $item->hasCanAccessSelectedRecords());
    }
}
