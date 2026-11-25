<?php

declare(strict_types=1);

namespace Modules\Core\Filament\AppPages\Concerns\Lists;

use Closure;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Cancel;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Js;

/**
 * The legacy buttons of an app list (menuActions(), ボタンの説明 menuHelp()), drawn right of 一覧 / 絞り込む; buttons
 * on the checked rows (onCheckedRows()) and those needing certain records (needsRows()).
 *
 * Part of Modules\Core\Filament\AppPages\AppListRecords (the shared app list); uses its views, fields and access rights.
 */
trait LegacyButtons
{
    /**
     * The app's legacy list buttons (「ボタンをメニュー位置に配置する」 一覧画面メニューの右側), drawn as buttons right of 一覧 /
     * 絞り込む (core::filament.app-header). A button working on the checked rows goes through onCheckedRows().
     *
     * @return list<Action>
     */
    protected function menuActions(): array
    {
        return [];
    }

    /**
     * A menu button working on the rows checked in the list (legacy 「一覧で選択されたレコードを取得する」): its action gets
     * them as `$records`. The app's table shows the check boxes (`Table::selectable()`) for whoever sees such a button.
     */
    protected static function onCheckedRows(Action $action): Action
    {
        $name = $action->getName();

        // the checked rows live in the table's Alpine component: mount the action through it so they are sent along
        return $action->accessSelectedRecords()
            ->alpineClickHandler(
                "(t => t ? Alpine.\$data(t).mountAction('{$name}') : \$wire.mountAction('{$name}'))(\$wire.\$el.querySelector('[x-data^=filamentTable]'))",
            )
            // nothing checked: the legacy warning instead of the dialog
            ->mountUsing(function (Action $action, ?Schema $schema): void {
                if ($action->getSelectedRecords()->isEmpty()) {
                    Notification::make()->warning()->title('レコードを選択してください。')->send();

                    throw new Cancel;
                }

                $schema?->fill();
            });
    }

    /**
     * A checked-row button that needs certain records ($scope, e.g. sales with a 納品書): disabled while the rows it
     * would work on — the checked rows, or the list as filtered and searched when none is checked — hold none of them.
     * The checked rows are judged in the browser ($store.sfkChecked) against the list's records outside $scope; with
     * more than NEEDS_ROWS_LIMIT of those, checked rows always leave the button on (its action still checks them).
     *
     * @param  Closure(Builder<Model>): mixed  $scope
     */
    protected function needsRows(Action $action, Closure $scope): Action
    {
        return $action->extraAttributes(fn (): array => ['x-bind:disabled' => $this->needsRowsExpression($scope)], merge: true);
    }

    private const NEEDS_ROWS_LIMIT = 2000;

    /** @var array<string, string> needsRows() expressions of this request, by the scope's query */
    private array $needsRowsExpressions = [];

    /** @param  Closure(Builder<Model>): mixed  $scope */
    private function needsRowsExpression(Closure $scope): string
    {
        $query = $this->getFilteredTableQuery();
        if (! $query instanceof Builder) {
            return 'false';
        }

        $matching = $query->clone()->where(fn (Builder $query) => $scope($query));
        $memo = $matching->toRawSql();
        if (isset($this->needsRowsExpressions[$memo])) {
            return $this->needsRowsExpressions[$memo];
        }

        if (! $matching->exists()) {
            return $this->needsRowsExpressions[$memo] = 'true';
        }

        $keyName = $query->getModel()->getQualifiedKeyName();
        $others = $query->clone()->reorder()->whereNot(fn (Builder $query) => $scope($query))
            ->limit(self::NEEDS_ROWS_LIMIT + 1)->pluck($keyName)->map(fn (mixed $key): string => (string) $key)->all();

        return $this->needsRowsExpressions[$memo] = count($others) > self::NEEDS_ROWS_LIMIT
            ? 'false'
            : '(c => c.count > 0 && ! c.all && c.keys.every(k => '.Js::from($others).'.includes(k)))($store.sfkChecked)';
    }

    /**
     * The legacy text under the list menu (「メニュー位置に文字を表示する」 一覧画面メニューの下側) as button label =>
     * explanation, shown in the collapsible 「ボタンの説明」 panel; empty for none.
     *
     * @return array<string, string>
     */
    public function menuHelp(): array
    {
        return [];
    }
}
